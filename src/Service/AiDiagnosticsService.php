<?php
declare(strict_types=1);

namespace App\Service;

use App\Model\Table\AlertsTable;
use App\Model\Table\MetricsTable;
use Cake\Http\Client;
use Cake\Log\Log;
use Throwable;

class AiDiagnosticsService
{
    /**
     * OpenRouter chat completions endpoint URL.
     */
    private const OPENROUTER_ENDPOINT = 'https://openrouter.ai/api/v1/chat/completions';

    /**
     * Default LLM model (free tier on OpenRouter).
     * Override via OPENROUTER_MODEL environment variable.
     */
    private const DEFAULT_MODEL = 'mistralai/mistral-7b-instruct';

    /**
     * Maximum number of recent metrics to fetch and include in the prompt.
     * Caps prompt length to keep latency predictable.
     */
    private const MAX_METRICS = 20;

    /**
     * HTTP request timeout in seconds for the OpenRouter API call.
     * Kept short so a slow API never blocks the page response for long.
     */
    private const HTTP_TIMEOUT = 5;

    /**
     * System prompt for the diagnosis call.
     *
     * Written as a domain expert rather than a generic SRE assistant, because a
     * model told only to "analyse metrics" produces exactly the answer this
     * feature exists to avoid: something is wrong, check the logs, contact
     * support. That output is worse than none — it costs a call and tells the
     * operator nothing they did not already know from the red light.
     *
     * The prompt therefore supplies the causal reasoning the model cannot infer
     * from metric names alone: which SDI rejection codes exist, and above all
     * that a lag without rejections and a rejection spike without lag are
     * opposite failures with opposite remedies. Naming a plausible cause is
     * required; deferring to support is explicitly forbidden.
     */
    private const SYSTEM_PROMPT = <<<'PROMPT'
        You are an operations expert for Italian electronic invoicing through the SDI
        (Sistema di Interscambio), the Agenzia delle Entrate platform that receives
        FatturaPA files. You are reading live metrics from a monitoring system and
        writing for an operator who needs to know what to do next.

        How to read the metrics:
        - sdi_receipt_lag_minutes: minutes since the oldest transmitted invoice still
          has no receipt. The SDI answers promptly when healthy, so sustained lag
          means files are leaving and nothing is coming back.
        - sdi_rejection_rate: percentage of transmissions answered with a Notifica di
          Scarto. A low steady rate is normal; a step change means one systematic
          cause across a batch, not many independent mistakes.
        - invoices_pending: invoices awaiting an outcome. Meaningless on its own —
          interpret it against the lag.
        - signing_cert_expiry_days: days of certificate validity remaining. Falling
          is bad. Once it reaches zero every transmission is refused at once.
        - cpu_usage, memory_usage: the machines doing the work. Rarely the story on
          their own, often the explanation for a lag.

        The distinction that matters most:
        - Lag high, rejections low  → the channel is stalled. The files were
          accepted; the answers are missing. Re-sending does not help.
        - Rejections high, lag low   → the payload is being refused. The channel
          works fine. Re-sending the same files does not help either.

        Rejection codes worth naming when the evidence fits:
        - 00100 certificato di firma scaduto — expired signing certificate
        - 00002 nome file duplicato — that file name was already transmitted
          (about the file name, not the invoice number)
        - 00200 file non conforme al formato — fails the FatturaPA schema
        - 003xx family — transmitter or recipient identification data

        Answer in at most four sentences: state the situation, name the most likely
        cause and say why the evidence points there, then give one concrete action.
        Never answer with "check the logs" or "contact support" — if the evidence is
        genuinely ambiguous, say which single metric or file would settle it.
        PROMPT;

    /**
     * Maximum number of metric lines to include in the LLM prompt.
     * Reduces token usage; MAX_METRICS is the outer DB fetch cap.
     */
    private const PROMPT_METRICS_LIMIT = 10;

    /**
     * Rule-based fallback thresholds keyed by metric name.
     *
     * Mirrors AlertsService::DEFAULT_THRESHOLDS at the 'high' level, including
     * the direction flag: signing_cert_expiry_days is unhealthy when it falls,
     * so comparing it upwards would report a valid certificate as a problem.
     *
     * @var array<string, array{threshold: float, label: string, direction?: string}>
     */
    private const FALLBACK_RULES = [
        'sdi_receipt_lag_minutes' => ['threshold' => 30.0, 'label' => 'Receipt lag'],
        'sdi_rejection_rate' => ['threshold' => 5.0, 'label' => 'Rejection rate'],
        'invoices_pending' => ['threshold' => 500.0, 'label' => 'Invoices pending'],
        'signing_cert_expiry_days' => [
            'threshold' => 30.0,
            'label' => 'Signing certificate validity',
            'direction' => 'below',
        ],
        'cpu_usage' => ['threshold' => 80.0, 'label' => 'CPU usage'],
        'memory_usage' => ['threshold' => 85.0, 'label' => 'Memory usage'],
        'error_rate' => ['threshold' => 5.0, 'label' => 'Error rate'],
    ];

    /**
     * Metric names whose combination identifies a failure mode.
     */
    private const M_LAG = 'sdi_receipt_lag_minutes';
    private const M_REJECT = 'sdi_rejection_rate';
    private const M_PENDING = 'invoices_pending';
    private const M_CERT = 'signing_cert_expiry_days';
    private const M_CPU = 'cpu_usage';
    private const M_MEMORY = 'memory_usage';

    /**
     * Rejection rate above which a systematic cause is assumed rather than
     * ordinary operator error. Individual mistakes produce a low, steady rate;
     * a step change points at one cause affecting a whole batch.
     */
    private const REJECTION_SYSTEMATIC = 5.0;

    /**
     * Rejection rate above which practically nothing is getting through, which
     * narrows the cause to something rejecting every file — a certificate or a
     * malformed template rather than bad data on individual invoices.
     */
    private const REJECTION_TOTAL = 50.0;

    /**
     * Receipt lag, in minutes, beyond which the channel is treated as stalled
     * rather than merely slow.
     */
    private const LAG_STALLED = 30.0;

    /**
     * Days of certificate validity below which renewal is already urgent.
     */
    private const CERT_URGENT = 7.0;

    /**
     * Infrastructure saturation level that plausibly explains a receipt lag.
     */
    private const SATURATION = 90.0;

    /**
     * @param \App\Model\Table\MetricsTable $metricsTable Source of recent metric events.
     * @param \App\Model\Table\AlertsTable $alertsTable Source of open alerts.
     * @param \Cake\Http\Client|null $httpClient Injected HTTP client for testing;
     * a default Client is created when null.
     */
    public function __construct(
        private readonly MetricsTable $metricsTable,
        private readonly AlertsTable $alertsTable,
        private ?Client $httpClient = null,
    ) {
    }

    /**
     * Produce a diagnosis of the current system state.
     *
     * Attempts the OpenRouter API when OPENROUTER_API_KEY is configured.
     * Falls back silently to the deterministic rule engine on key absence,
     * non-2xx response, JSON parse failure, or any thrown exception.
     *
     * @param string $correlationId Request correlation ID for audit logging.
     * @return \App\Service\DiagnosisResult Immutable result carrying diagnosis, source, and metadata.
     */
    public function diagnose(string $correlationId): DiagnosisResult
    {
        $metrics = $this->fetchRecentMetrics();
        $alerts = $this->fetchOpenAlerts();
        $apiKey = (string)env('OPENROUTER_API_KEY', '');

        if ($apiKey !== '') {
            $aiResult = $this->callOpenRouter($apiKey, $metrics, $alerts, $correlationId);
            if ($aiResult !== null) {
                return $aiResult;
            }
        }

        // No API key, or AI call failed — deterministic fallback always succeeds.
        return $this->deterministicDiagnosis($metrics, $alerts, $correlationId);
    }

    /**
     * Fetch the last MAX_METRICS metric events, most recently measured first.
     *
     * Ordered by recorded_at rather than created: the former is when the reading
     * was taken, the latter merely when this system stored it. The distinction
     * matters because both columns have one-second granularity, and a burst of
     * SQS messages — or a scenario run — lands several readings inside the same
     * second. Ordering by created then leaves rows in arbitrary order, and
     * anything reading "the latest value per metric" can silently pick a stale
     * one. Sorting by id as a tiebreaker makes the order total, so the result is
     * reproducible even when two readings share a timestamp.
     *
     * @return array<\App\Model\Entity\Metric> Recent metric entity array.
     */
    private function fetchRecentMetrics(): array
    {
        /** @var array<\App\Model\Entity\Metric> $metrics */
        $metrics = $this->metricsTable
            ->find()
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->limit(self::MAX_METRICS)
            ->all()
            ->toArray();

        return $metrics;
    }

    /**
     * Fetch all open alerts ordered by severity (critical first).
     *
     * @return array<\App\Model\Entity\Alert> Open alert entity array.
     */
    private function fetchOpenAlerts(): array
    {
        /** @var array<\App\Model\Entity\Alert> $alerts */
        $alerts = $this->alertsTable
            ->find('open')
            ->all()
            ->toArray();

        return $alerts;
    }

    /**
     * Attempt to call the OpenRouter API and return a DiagnosisResult.
     *
     * Logs every attempt and its outcome for the audit trail. Returns null
     * on HTTP error, empty content, JSON decode failure, or any exception,
     * which signals the caller to activate the fallback.
     *
     * @param string $apiKey OPENROUTER_API_KEY value (never logged).
     * @param array<\App\Model\Entity\Metric> $metrics Recent metric entities.
     * @param array<\App\Model\Entity\Alert> $alerts Open alert entities.
     * @param string $correlationId Request correlation ID.
     * @return \App\Service\DiagnosisResult|null Populated result on success, null on any failure.
     */
    private function callOpenRouter(
        string $apiKey,
        array $metrics,
        array $alerts,
        string $correlationId,
    ): ?DiagnosisResult {
        $model = (string)(env('OPENROUTER_MODEL', '') ?: self::DEFAULT_MODEL);
        $prompt = $this->buildPrompt($metrics, $alerts);

        // Audit log: record every attempt so the Log Viewer shows AI usage history.
        Log::info('AI diagnostics call initiated', [
            'correlation_id' => $correlationId,
            'model' => $model,
            'metrics_count' => count($metrics),
            'alerts_count' => count($alerts),
        ]);

        try {
            $client = $this->httpClient ?? new Client(['timeout' => self::HTTP_TIMEOUT]);

            $response = $client->post(
                self::OPENROUTER_ENDPOINT,
                (string)json_encode([
                    'model' => $model,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => self::SYSTEM_PROMPT,
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                ]),
                [
                    'headers' => [
                        // API key is passed only in the header — never in the URL or body.
                        'Authorization' => 'Bearer ' . $apiKey,
                        'Content-Type' => 'application/json',
                    ],
                    'timeout' => self::HTTP_TIMEOUT,
                ],
            );

            if (!$response->isOk()) {
                Log::warning('AI diagnostics call returned non-2xx response', [
                    'correlation_id' => $correlationId,
                    'status_code' => $response->getStatusCode(),
                ]);

                return null;
            }

            $data = json_decode((string)$response->getBody(), true);
            $text = (string)($data['choices'][0]['message']['content'] ?? '');

            if ($text === '') {
                Log::warning('AI diagnostics call returned empty content', [
                    'correlation_id' => $correlationId,
                ]);

                return null;
            }

            Log::info('AI diagnostics call succeeded', [
                'correlation_id' => $correlationId,
                'model' => $model,
                'source' => 'ai',
            ]);

            return new DiagnosisResult(
                diagnosis: $text,
                source: 'ai',
                model: $model,
                correlationId: $correlationId,
                generatedAt: gmdate('Y-m-d\TH:i:s\Z'),
                metricsCount: count($metrics),
                alertsCount: count($alerts),
            );
        } catch (Throwable $e) {
            Log::warning('AI diagnostics call threw exception, activating fallback', [
                'correlation_id' => $correlationId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Produce a rule-based diagnosis without any external API calls.
     *
     * Iterates metrics against FALLBACK_RULES thresholds. Each (name, source)
     * pair is reported at most once to avoid flooding the diagnosis with
     * repeated readings from the same host. Open alert count is appended
     * when non-zero.
     *
     * @param array<\App\Model\Entity\Metric> $metrics Recent metric entities.
     * @param array<\App\Model\Entity\Alert> $alerts Open alert entities.
     * @param string $correlationId Request correlation ID.
     * @return \App\Service\DiagnosisResult Always succeeds — no external dependencies.
     */
    private function deterministicDiagnosis(
        array $metrics,
        array $alerts,
        string $correlationId,
    ): DiagnosisResult {
        $latest = $this->latestValuePerMetric($metrics);
        $pattern = $this->matchFailureMode($latest);
        $issues = $this->collectThresholdBreaches($metrics);

        if (count($alerts) > 0) {
            $issues[] = sprintf('%d open alert(s) require attention.', count($alerts));
        }

        if ($pattern !== null) {
            // A recognised failure mode explains the numbers, so it replaces the
            // raw breach list rather than being appended to it.
            $diagnosis = $pattern;
        } elseif (empty($issues)) {
            $diagnosis = empty($metrics)
                ? 'No recent metrics found. Either the flow is idle or the collector has stopped '
                    . 'reporting — check that the SQS poller is running before assuming all is well.'
                : 'All sampled metrics are within normal thresholds and no alerts are open. '
                    . 'Invoices are reaching the SDI and receipts are coming back.';
        } else {
            $diagnosis = 'Issues detected: ' . implode(' | ', $issues);
        }

        Log::info('AI diagnostics: deterministic fallback applied', [
            'correlation_id' => $correlationId,
            'failure_mode_matched' => $pattern !== null,
            'issues_count' => count($issues),
            'metrics_count' => count($metrics),
            'alerts_count' => count($alerts),
        ]);

        return new DiagnosisResult(
            diagnosis: $diagnosis,
            source: 'fallback',
            model: 'deterministic-fallback',
            correlationId: $correlationId,
            generatedAt: gmdate('Y-m-d\TH:i:s\Z'),
            metricsCount: count($metrics),
            alertsCount: count($alerts),
        );
    }

    /**
     * Reduce the metric stream to the most recent value for each metric name.
     *
     * Metrics arrive most-recently-measured first, so the first sighting of a
     * name wins — which is only correct because fetchRecentMetrics() imposes a
     * total order; see the note there. Values
     * are collapsed across sources deliberately: the failure modes below are
     * about the flow as a whole, and a per-node breakdown would obscure the
     * fact that, say, receipts are lagging everywhere at once.
     *
     * @param array<\App\Model\Entity\Metric> $metrics Recent metric entities, newest first.
     * @return array<string, float> Latest value keyed by metric name.
     */
    private function latestValuePerMetric(array $metrics): array
    {
        $latest = [];

        foreach ($metrics as $metric) {
            $name = (string)$metric->name;

            if (!array_key_exists($name, $latest)) {
                $latest[$name] = (float)$metric->value;
            }
        }

        return $latest;
    }

    /**
     * Identify a known SDI failure mode from the combination of current values.
     *
     * This is the part that makes the fallback worth having. A per-metric
     * threshold list can only report that numbers are large; it cannot tell an
     * operator what broke. The same "8,000 invoices pending" reading means
     * entirely different things depending on what sits next to it:
     *
     *   lag high, rejections low   → files leave, nothing comes back: the
     *                                transmission channel is stalled. Not a
     *                                data problem, so re-sending will not help.
     *   rejections high, cert low  → the SDI is refusing everything with 00100.
     *   rejections high, cert fine → the payload is being refused: schema
     *                                (00200) or identification data (003xx).
     *   cert low, rest healthy     → nothing is broken yet, and that is exactly
     *                                why this is the most useful alert here.
     *
     * Ordered by specificity: the certificate cases come first because they
     * explain a high rejection rate rather than merely coinciding with it.
     *
     * @param array<string, float> $latest Latest value per metric name.
     * @return string|null A diagnosis with a recommended action, or null if no mode matches.
     */
    private function matchFailureMode(array $latest): ?string
    {
        $lag = $latest[self::M_LAG] ?? null;
        $reject = $latest[self::M_REJECT] ?? null;
        $pending = $latest[self::M_PENDING] ?? null;
        $cert = $latest[self::M_CERT] ?? null;
        $cpu = $latest[self::M_CPU] ?? null;
        $memory = $latest[self::M_MEMORY] ?? null;

        // Certificate already expired or expiring, with transmissions failing.
        if ($cert !== null && $cert <= self::CERT_URGENT && $reject !== null && $reject >= self::REJECTION_TOTAL) {
            return sprintf(
                'Rejection rate is %.1f%% and the signing certificate has %.0f day(s) of validity left. '
                . 'The SDI is almost certainly refusing every file with code 00100 (certificato di firma '
                . 'scaduto), which stops the whole flow at once rather than degrading gradually. '
                . 'Action: renew the signing certificate, then re-transmit the rejected batch — the invoices '
                . 'themselves are valid and may keep their numbers, but each file must be renamed to avoid '
                . 'code 00002 (nome file duplicato).',
                $reject,
                $cert,
            );
        }

        // Everything rejected, certificate healthy: the payload is the problem.
        if ($reject !== null && $reject >= self::REJECTION_TOTAL) {
            return sprintf(
                'Rejection rate is %.1f%% while the signing certificate is valid. A near-total rejection '
                . 'rate points at the payload rather than at individual invoices: check the FatturaPA schema '
                . 'version in use (code 00200, file non conforme al formato) and the transmitter and '
                . 'recipient identification data (003xx family). '
                . 'Action: pull one rejected file and read its Notifica di Scarto — the code names the '
                . 'failing control precisely.',
                $reject,
            );
        }

        // Files leaving, nothing coming back, and rejections are not the cause.
        if ($lag !== null && $lag >= self::LAG_STALLED && ($reject === null || $reject < self::REJECTION_SYSTEMATIC)) {
            $saturated = ($cpu !== null && $cpu >= self::SATURATION)
                || ($memory !== null && $memory >= self::SATURATION);

            $cause = $saturated
                ? 'Infrastructure is saturated at the same time, which is the likely cause rather than a '
                    . 'coincidence: the nodes cannot keep up with processing the receipts they receive. '
                    . 'Action: check capacity on the batch and validator nodes before looking at the SDI side.'
                : 'Infrastructure looks healthy, so the stall is on the channel rather than in this system. '
                    . 'Action: verify the SDI channel status and the intermediary transmission queue. '
                    . 'Re-sending will not help — the files were accepted, the answers are missing.';

            return sprintf(
                'No receipts for %.0f minutes with a rejection rate of %.1f%%%s. Files are being transmitted '
                . 'and are not being refused, so this is a stalled queue and not a data problem. %s',
                $lag,
                $reject ?? 0.0,
                $pending !== null ? sprintf(' and %.0f invoices awaiting an outcome', $pending) : '',
                $cause,
            );
        }

        // Certificate running out while the flow is still healthy — the one
        // alert here that arrives before anything has broken.
        if ($cert !== null && $cert <= self::FALLBACK_RULES[self::M_CERT]['threshold']) {
            return sprintf(
                'The flow is currently healthy, but the signing certificate expires in %.0f day(s). '
                . 'Nothing has failed yet: this is the warning that arrives before the outage rather than '
                . 'after it. Once the certificate lapses the SDI refuses every transmission with code 00100 '
                . 'and there is no partial degradation to notice first. '
                . 'Action: start renewal with the certification authority now — reissue takes days, not hours.',
                $cert,
            );
        }

        return null;
    }

    /**
     * Report each breached threshold once per (metric, source) pair.
     *
     * Used when no known failure mode matches, so the operator still sees the
     * raw numbers rather than a bare "something is wrong".
     *
     * @param array<\App\Model\Entity\Metric> $metrics Recent metric entities.
     * @return list<string> Human-readable breach descriptions.
     */
    private function collectThresholdBreaches(array $metrics): array
    {
        $issues = [];
        // Tracks already-reported (metric_name|source) pairs to avoid duplicates.
        $reported = [];

        foreach ($metrics as $metric) {
            $name = (string)$metric->name;
            $value = (float)$metric->value;
            $src = (string)$metric->source;
            $key = $name . '|' . $src;

            if (!isset(self::FALLBACK_RULES[$name]) || isset($reported[$key])) {
                continue;
            }

            $rule = self::FALLBACK_RULES[$name];
            $below = ($rule['direction'] ?? 'above') === 'below';
            $breached = $below ? $value <= $rule['threshold'] : $value >= $rule['threshold'];

            if ($breached) {
                $issues[] = sprintf(
                    '%s on %s: %.1f (%s %.1f)',
                    $rule['label'],
                    $src,
                    $value,
                    $below ? 'below' : 'threshold:',
                    $rule['threshold'],
                );
                $reported[$key] = true;
            }
        }

        return $issues;
    }

    /**
     * Extract the SDI rejection code from a metric's JSON tag payload.
     *
     * Tags are persisted as a JSON string by the ORM, so they need decoding
     * before use. Malformed JSON is treated as "no code" rather than raising:
     * a diagnosis is still worth producing without one, and a monitoring
     * feature that dies on a bad tag is worse than one that degrades.
     *
     * A healthy transmission has no rejection code — it produces a Ricevuta di
     * Consegna — so a null result is the normal case, not an error.
     *
     * @param string|null $tags Raw JSON tag payload from the Metric entity.
     * @return string|null The rejection code, or null when absent or undecodable.
     */
    private function extractSdiError(?string $tags): ?string
    {
        if ($tags === null || $tags === '') {
            return null;
        }

        $decoded = json_decode($tags, true);

        if (!is_array($decoded) || !isset($decoded['sdi_error'])) {
            return null;
        }

        $code = $decoded['sdi_error'];

        return is_scalar($code) ? (string)$code : null;
    }

    /**
     * Build the LLM prompt from recent metrics and open alerts.
     *
     * Caps metric lines at PROMPT_METRICS_LIMIT to keep token count manageable.
     * The service already limits DB fetch to MAX_METRICS; this is an additional
     * prompt-level cap for token budget reasons.
     *
     * @param array<\App\Model\Entity\Metric> $metrics Recent metric entities.
     * @param array<\App\Model\Entity\Alert> $alerts Open alert entities.
     * @return string Formatted user prompt string.
     */
    private function buildPrompt(array $metrics, array $alerts): string
    {
        $metricLines = [];
        foreach (array_slice($metrics, 0, self::PROMPT_METRICS_LIMIT) as $metric) {
            $sdiError = $this->extractSdiError($metric->tags);

            $metricLines[] = sprintf(
                '- %s / %s: %.2f %s%s',
                (string)$metric->source,
                (string)$metric->name,
                (float)$metric->value,
                (string)($metric->unit ?? ''),
                $sdiError !== null ? sprintf(' [SDI rejection code %s]', $sdiError) : '',
            );
        }

        $alertLines = [];
        foreach ($alerts as $alert) {
            $alertLines[] = sprintf(
                '- [%s] %s',
                strtoupper((string)$alert->severity),
                (string)$alert->message,
            );
        }

        return sprintf(
            "Current readings from the SDI/FatturaPA flow.\n\n"
            . "Recent metrics (newest first):\n%s\n\nOpen alerts:\n%s\n\n"
            . 'What is happening, what is the most likely cause, and what should the operator do?',
            empty($metricLines) ? '(none)' : implode("\n", $metricLines),
            empty($alertLines) ? '(none)' : implode("\n", $alertLines),
        );
    }
}
