<?php
declare(strict_types=1);

namespace App\Service;

use App\Model\Table\AlertsTable;
use App\Model\Table\MetricsTable;
use Cake\Http\Client;
use Cake\Log\Log;
use Throwable;

/**
 * DiagnosisResult — immutable value object returned by AiDiagnosticsService::diagnose().
 *
 * Carries the outcome of a single AI diagnostics request: the diagnosis text,
 * the source (AI or deterministic fallback), the model used, timing information,
 * and the input counts so the view can display context alongside the diagnosis.
 *
 * Defined alongside AiDiagnosticsService because it is a pure data carrier with
 * no independent lifecycle; it is always constructed inside the service.
 */
final class DiagnosisResult
{
    /**
     * @param string $diagnosis     Natural-language diagnosis text.
     * @param string $source        Origin of the diagnosis: 'ai' or 'fallback'.
     * @param string $model         LLM model identifier, or 'deterministic-fallback'.
     * @param string $correlationId Correlation ID propagated from the HTTP request.
     * @param string $generatedAt   ISO-8601 UTC timestamp of generation.
     * @param int    $metricsCount  Number of recent metric events analysed.
     * @param int    $alertsCount   Number of open alerts included in the analysis.
     */
    public function __construct(
        public readonly string $diagnosis,
        public readonly string $source,
        public readonly string $model,
        public readonly string $correlationId,
        public readonly string $generatedAt,
        public readonly int $metricsCount,
        public readonly int $alertsCount,
    ) {
    }
}

/**
 * AiDiagnosticsService — OpenRouter AI diagnostics with deterministic fallback.
 *
 * Fetches the last MAX_METRICS metric events and all open alerts, builds a
 * structured prompt, and requests a diagnosis from an OpenRouter-compatible LLM.
 * If OPENROUTER_API_KEY is absent, or the API call fails for any reason,
 * the service transparently activates the rule-based fallback engine.
 *
 * Fallback threshold rules (keyed by metric name):
 *   cpu_usage    >= 80.0  → "CPU usage" threshold exceeded
 *   memory_usage >= 85.0  → "Memory usage" threshold exceeded
 *   error_rate   >= 5.0   → "Error rate" threshold exceeded
 *
 * Every AI call attempt, its outcome, and every fallback activation are written
 * as structured JSON audit log entries so SRE operators can trace diagnostics
 * requests in the Log Viewer (correlation_id propagated on every entry).
 *
 * The HTTP client is accepted as an optional constructor argument to enable
 * unit testing without making real network calls. When null, a default
 * Cake\Http\Client is instantiated with HTTP_TIMEOUT-second timeout.
 */
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
     * Maximum number of metric lines to include in the LLM prompt.
     * Reduces token usage; MAX_METRICS is the outer DB fetch cap.
     */
    private const PROMPT_METRICS_LIMIT = 10;

    /**
     * Rule-based fallback thresholds keyed by metric name.
     *
     * @var array<string, array{threshold: float, label: string}>
     */
    private const FALLBACK_RULES = [
        'cpu_usage'    => ['threshold' => 80.0, 'label' => 'CPU usage'],
        'memory_usage' => ['threshold' => 85.0, 'label' => 'Memory usage'],
        'error_rate'   => ['threshold' => 5.0,  'label' => 'Error rate'],
    ];

    /**
     * @param \App\Model\Table\MetricsTable $metricsTable Source of recent metric events.
     * @param \App\Model\Table\AlertsTable  $alertsTable  Source of open alerts.
     * @param \Cake\Http\Client|null        $httpClient   Injected HTTP client for testing;
     *                                                    a default Client is created when null.
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
     * @param string         $correlationId Request correlation ID for audit logging.
     * @return \App\Service\DiagnosisResult Immutable result carrying diagnosis, source, and metadata.
     */
    public function diagnose(string $correlationId): DiagnosisResult
    {
        $metrics = $this->fetchRecentMetrics();
        $alerts  = $this->fetchOpenAlerts();
        $apiKey  = (string)env('OPENROUTER_API_KEY', '');

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
     * Fetch the last MAX_METRICS metric events ordered by insertion time (newest first).
     *
     * @return array<\App\Model\Entity\Metric> Recent metric entity array.
     */
    private function fetchRecentMetrics(): array
    {
        return $this->metricsTable
            ->find()
            ->orderByDesc('created')
            ->limit(self::MAX_METRICS)
            ->all()
            ->toArray();
    }

    /**
     * Fetch all open alerts ordered by severity (critical first).
     *
     * @return array<\App\Model\Entity\Alert> Open alert entity array.
     */
    private function fetchOpenAlerts(): array
    {
        return $this->alertsTable
            ->find('open')
            ->all()
            ->toArray();
    }

    /**
     * Attempt to call the OpenRouter API and return a DiagnosisResult.
     *
     * Logs every attempt and its outcome for the audit trail. Returns null
     * on HTTP error, empty content, JSON decode failure, or any exception,
     * which signals the caller to activate the fallback.
     *
     * @param string                              $apiKey        OPENROUTER_API_KEY value (never logged).
     * @param array<\App\Model\Entity\Metric>     $metrics       Recent metric entities.
     * @param array<\App\Model\Entity\Alert>      $alerts        Open alert entities.
     * @param string                              $correlationId Request correlation ID.
     * @return \App\Service\DiagnosisResult|null                Populated result on success, null on any failure.
     */
    private function callOpenRouter(
        string $apiKey,
        array $metrics,
        array $alerts,
        string $correlationId,
    ): ?DiagnosisResult {
        $model  = (string)(env('OPENROUTER_MODEL', '') ?: self::DEFAULT_MODEL);
        $prompt = $this->buildPrompt($metrics, $alerts);

        // Audit log: record every attempt so the Log Viewer shows AI usage history.
        Log::info('AI diagnostics call initiated', [
            'correlation_id' => $correlationId,
            'model'          => $model,
            'metrics_count'  => count($metrics),
            'alerts_count'   => count($alerts),
        ]);

        try {
            $client = $this->httpClient ?? new Client(['timeout' => self::HTTP_TIMEOUT]);

            $response = $client->post(
                self::OPENROUTER_ENDPOINT,
                (string)json_encode([
                    'model'    => $model,
                    'messages' => [
                        [
                            'role'    => 'system',
                            'content' => 'You are an SRE assistant. Analyse the provided system metrics and alerts, then give a concise operational diagnosis in plain English (max 3 sentences).',
                        ],
                        [
                            'role'    => 'user',
                            'content' => $prompt,
                        ],
                    ],
                ]),
                [
                    'headers' => [
                        // API key is passed only in the header — never in the URL or body.
                        'Authorization' => 'Bearer ' . $apiKey,
                        'Content-Type'  => 'application/json',
                    ],
                    'timeout' => self::HTTP_TIMEOUT,
                ],
            );

            if (!$response->isOk()) {
                Log::warning('AI diagnostics call returned non-2xx response', [
                    'correlation_id' => $correlationId,
                    'status_code'    => $response->getStatusCode(),
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
                'model'          => $model,
                'source'         => 'ai',
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
                'error'          => $e->getMessage(),
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
     * @param array<\App\Model\Entity\Metric> $metrics       Recent metric entities.
     * @param array<\App\Model\Entity\Alert>  $alerts        Open alert entities.
     * @param string                          $correlationId Request correlation ID.
     * @return \App\Service\DiagnosisResult                 Always succeeds — no external dependencies.
     */
    private function deterministicDiagnosis(
        array $metrics,
        array $alerts,
        string $correlationId,
    ): DiagnosisResult {
        $issues = [];
        // Tracks already-reported (metric_name|source) pairs to avoid duplicates.
        $reported = [];

        foreach ($metrics as $metric) {
            $name  = (string)$metric->name;
            $value = (float)$metric->value;
            $src   = (string)$metric->source;
            $key   = $name . '|' . $src;

            // Skip metrics with no threshold rule or already reported for this source.
            if (!isset(self::FALLBACK_RULES[$name]) || isset($reported[$key])) {
                continue;
            }

            $rule = self::FALLBACK_RULES[$name];
            if ($value >= $rule['threshold']) {
                $issues[]     = sprintf(
                    '%s on %s: %.1f (threshold: %.1f)',
                    $rule['label'],
                    $src,
                    $value,
                    $rule['threshold'],
                );
                $reported[$key] = true;
            }
        }

        if (count($alerts) > 0) {
            $issues[] = sprintf('%d open alert(s) require attention.', count($alerts));
        }

        if (empty($issues)) {
            $diagnosis = empty($metrics)
                ? 'No recent metrics found. System may be idle or not reporting data.'
                : 'All sampled metrics are within normal thresholds and no alerts are open. System appears healthy.';
        } else {
            $diagnosis = 'Issues detected: ' . implode(' | ', $issues);
        }

        Log::info('AI diagnostics: deterministic fallback applied', [
            'correlation_id' => $correlationId,
            'issues_count'   => count($issues),
            'metrics_count'  => count($metrics),
            'alerts_count'   => count($alerts),
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
     * Build the LLM prompt from recent metrics and open alerts.
     *
     * Caps metric lines at PROMPT_METRICS_LIMIT to keep token count manageable.
     * The service already limits DB fetch to MAX_METRICS; this is an additional
     * prompt-level cap for token budget reasons.
     *
     * @param array<\App\Model\Entity\Metric> $metrics Recent metric entities.
     * @param array<\App\Model\Entity\Alert>  $alerts  Open alert entities.
     * @return string                          Formatted user prompt string.
     */
    private function buildPrompt(array $metrics, array $alerts): string
    {
        $metricLines = [];
        foreach (array_slice($metrics, 0, self::PROMPT_METRICS_LIMIT) as $metric) {
            $metricLines[] = sprintf(
                '- %s / %s: %.2f %s',
                (string)$metric->source,
                (string)$metric->name,
                (float)$metric->value,
                (string)($metric->unit ?? ''),
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
            "Analyse the following SDI Ops Monitor data and provide a concise health diagnosis (max 3 sentences):\n\n"
            . "Recent Metrics:\n%s\n\nOpen Alerts:\n%s\n\n"
            . 'What is the current operational health status?',
            empty($metricLines) ? '(none)' : implode("\n", $metricLines),
            empty($alertLines)  ? '(none)' : implode("\n", $alertLines),
        );
    }
}
