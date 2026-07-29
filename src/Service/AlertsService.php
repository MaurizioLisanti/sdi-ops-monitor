<?php
declare(strict_types=1);

namespace App\Service;

use App\Model\Entity\Alert;
use App\Model\Entity\Metric;
use App\Model\Table\AlertsTable;
use Cake\Core\Configure;
use Cake\Log\Log;
use RuntimeException;

/**
 * AlertsService — threshold evaluation engine for metric-based alerts.
 *
 * After a Metric is persisted, call evaluate() to check whether its value
 * exceeds any configured threshold. When it does, an Alert entity is created
 * and saved. The caller is responsible for wrapping evaluate() in a try/catch
 * so that alert creation failures never block the metric ingestion response.
 *
 * Threshold configuration (via app_local.php → Configure::read('Thresholds')):
 * ```php
 * 'Thresholds' => [
 * 'sdi_receipt_lag_minutes' => [
 * ['threshold' => 30.0, 'severity' => 'high'],
 * ['threshold' => 120.0, 'severity' => 'critical'],
 * ],
 * 'signing_cert_expiry_days' => [
 * ['threshold' => 30.0, 'severity' => 'high', 'direction' => 'below'],
 * ['threshold' => 7.0, 'severity' => 'critical', 'direction' => 'below'],
 * ],
 * ],
 * ```
 *
 * Threshold direction: rules default to 'above', which fires when the measured
 * value meets or exceeds the threshold — the right semantics for saturation
 * metrics such as CPU usage or queue depth. Some operational metrics are
 * healthy when high and dangerous when low: days remaining before the SDI
 * signing certificate expires is the canonical example, since an expired
 * certificate means every transmission is rejected with code 00100. Those
 * rules declare 'direction' => 'below' and fire when the value drops to or
 * under the threshold.
 *
 * Supported severity levels (ascending): low → medium → high → critical.
 */
class AlertsService
{
    /**
     * Severity levels in ascending order of importance.
     * Used to determine which threshold produces the highest severity.
     */
    private const SEVERITY_ORDER = ['low', 'medium', 'high', 'critical'];

    /**
     * Threshold comparison directions.
     *
     * ABOVE — fire when value >= threshold (saturation metrics).
     * BELOW — fire when value <= threshold (countdown metrics, e.g. days to expiry).
     */
    private const DIRECTION_ABOVE = 'above';
    private const DIRECTION_BELOW = 'below';

    /**
     * Direction assumed when a rule does not declare one.
     *
     * Defaulting to ABOVE keeps every pre-existing rule-set working unchanged,
     * so adding direction support is not a breaking change for deployments that
     * already define Thresholds in app_local.php.
     */
    private const DEFAULT_DIRECTION = self::DIRECTION_ABOVE;

    /**
     * Built-in fallback thresholds used when Configure does not define the metric.
     *
     * Two tiers, deliberately separated:
     *
     * SERVICE metrics describe what the business actually cares about — are
     * invoices reaching the SDI and coming back accepted. These drive the
     * dashboard state, because they are the ones a non-technical operator can
     * act on.
     *
     * INFRASTRUCTURE metrics describe the machines doing the work. They rarely
     * matter on their own, but they are what turns "8,000 invoices are stuck"
     * into "the Milan batch node is saturated, which is why they are stuck".
     * Kept as diagnostic context rather than as the primary signal.
     *
     * Each entry is a list of rules; rules are evaluated independently and the
     * highest triggered severity wins. Every number below is a starting point
     * that deployments are expected to tune in app_local.php — the values are
     * documented with their reasoning so that tuning is an informed decision
     * rather than guesswork.
     *
     * @var array<string, list<array{threshold: float, severity: string, direction?: string}>>
     */
    private const DEFAULT_THRESHOLDS = [
        // ── Service metrics ──────────────────────────────────────────────
        /*
         * Minutes elapsed since the oldest transmitted invoice still without a
         * receipt. This is the single most informative metric in the system: a
         * healthy channel returns a Ricevuta di Consegna or a Notifica di Scarto
         * promptly, so a lag that keeps growing means transmissions are leaving
         * but nothing is coming back — a stuck channel rather than a data
         * problem. Half an hour is worth a look; two hours means the flow has
         * stopped and invoices are silently accumulating.
         */
        'sdi_receipt_lag_minutes' => [
            ['threshold' => 30.0, 'severity' => 'high'],
            ['threshold' => 120.0, 'severity' => 'critical'],
        ],

        /*
         * Percentage of transmissions answered with a Notifica di Scarto.
         * A residual rate is normal and irreducible: operators mistype a
         * recipient code, a customer's VAT registration lapses. What matters is
         * the jump, because a rejection rate that moves as a step function is
         * almost never many independent mistakes — it is one systematic cause
         * affecting a whole batch: an expired certificate, an XML template
         * change, stale registry data.
         */
        'sdi_rejection_rate' => [
            ['threshold' => 5.0, 'severity' => 'high'],
            ['threshold' => 15.0, 'severity' => 'critical'],
        ],

        /*
         * Invoices transmitted and still awaiting an outcome.
         *
         * Unlike the metrics above, this one has no meaningful universal value:
         * 500 pending is an emergency for a studio filing thirty invoices a day
         * and an ordinary Monday for a utility filing fifty thousand. The
         * defaults here suit a mid-sized intermediary and exist so the metric is
         * never silently unmonitored; sizing them against real traffic is the
         * first thing a deployment should do.
         */
        'invoices_pending' => [
            ['threshold' => 500.0, 'severity' => 'high'],
            ['threshold' => 2000.0, 'severity' => 'critical'],
        ],

        /*
         * Days remaining before the signing certificate expires — a countdown,
         * hence direction 'below'.
         *
         * This is the only metric here that predicts an outage instead of
         * reporting one. Once the certificate lapses the SDI refuses every
         * single file with code 00100 and the whole flow stops at once, with no
         * partial degradation to notice first. Renewal involves a certification
         * authority and takes days, so thirty days is when the paperwork should
         * start and seven days is already an emergency.
         */
        'signing_cert_expiry_days' => [
            ['threshold' => 30.0, 'severity' => 'high', 'direction' => 'below'],
            ['threshold' => 7.0, 'severity' => 'critical', 'direction' => 'below'],
        ],

        // ── Infrastructure metrics (diagnostic context) ──────────────────
        /*
         * Saturation of the nodes running ingestion and validation. Not a
         * FatturaPA compliance problem in itself, but the usual explanation
         * when sdi_receipt_lag_minutes climbs while rejections stay flat.
         */
        'cpu_usage' => [
            ['threshold' => 80.0, 'severity' => 'high'],
            ['threshold' => 95.0, 'severity' => 'critical'],
        ],
        'memory_usage' => [
            ['threshold' => 85.0, 'severity' => 'high'],
            ['threshold' => 95.0, 'severity' => 'critical'],
        ],
    ];

    private AlertsTable $alertsTable;

    /**
     * @param \App\Model\Table\AlertsTable $alertsTable The persistence layer for Alert entities.
     */
    public function __construct(AlertsTable $alertsTable)
    {
        $this->alertsTable = $alertsTable;
    }

    /**
     * Evaluate a metric value against configured thresholds and create an Alert if needed.
     *
     * Reads threshold rules from Configure::read('Thresholds.<metricName>'); falls back to
     * DEFAULT_THRESHOLDS when the key is absent. If the metric has no rules at all, returns
     * null immediately.
     *
     * When one or more thresholds are exceeded, the rule with the highest severity wins and
     * exactly one Alert is created with status='open'. The alert is persisted via AlertsTable.
     *
     * All outcomes are logged as single-line JSON entries compatible with Kibana/ELK.
     *
     * @param \App\Model\Entity\Metric $metric The metric entity just saved to the database.
     * @param string $correlationId X-Correlation-ID from the originating request.
     * @return \App\Model\Entity\Alert|null The created Alert on threshold breach, null otherwise.
     * @throws \RuntimeException When AlertsTable::save() fails (caller should log and continue).
     */
    public function evaluate(Metric $metric, string $correlationId = ''): ?Alert
    {
        $metricName = (string)$metric->name;
        $metricValue = (float)$metric->value;

        $rules = $this->resolveThresholds($metricName);

        if ($rules === null) {
            Log::debug(json_encode([
                'timestamp' => date('c'),
                'level' => 'debug',
                'correlation_id' => $correlationId,
                'message' => 'No thresholds configured for metric — skipping alert evaluation.',
                'context' => ['metric_name' => $metricName, 'metric_value' => $metricValue],
            ], JSON_THROW_ON_ERROR));

            return null;
        }

        $severity = $this->resolveHighestSeverity($rules, $metricValue);

        if ($severity === null) {
            Log::info(json_encode([
                'timestamp' => date('c'),
                'level' => 'info',
                'correlation_id' => $correlationId,
                'message' => 'Metric value is below all thresholds — no alert created.',
                'context' => ['metric_name' => $metricName, 'metric_value' => $metricValue],
            ], JSON_THROW_ON_ERROR));

            return null;
        }

        // Build the alert message. The verb follows the direction of the breached
        // rule: "exceeded" is plainly wrong for a countdown metric, where an alert
        // means the value has fallen too low rather than climbed too high.
        $message = sprintf(
            "Metric '%s' value %.4g %s %s threshold.",
            $metricName,
            $metricValue,
            $this->breachVerb($rules, $metricValue, $severity),
            $severity,
        );

        $alert = $this->alertsTable->newEntity([
            'metric_id' => $metric->id ?? null,
            'severity' => $severity,
            'message' => $message,
            'status' => 'open',
        ]);

        if (!$this->alertsTable->save($alert)) {
            $errorDetails = $alert->getErrors();

            Log::error(json_encode([
                'timestamp' => date('c'),
                'level' => 'error',
                'correlation_id' => $correlationId,
                'message' => 'Failed to persist alert entity.',
                'context' => [
                    'metric_name' => $metricName,
                    'metric_value' => $metricValue,
                    'severity' => $severity,
                    'entity_errors' => $errorDetails,
                ],
            ], JSON_THROW_ON_ERROR));

            throw new RuntimeException(
                sprintf('AlertsService: could not save alert for metric "%s".', $metricName),
            );
        }

        Log::warning(json_encode([
            'timestamp' => date('c'),
            'level' => 'warning',
            'correlation_id' => $correlationId,
            'message' => 'Alert created — metric threshold breached.',
            'context' => [
                'alert_id' => $alert->id,
                'metric_name' => $metricName,
                'metric_value' => $metricValue,
                'severity' => $severity,
            ],
        ], JSON_THROW_ON_ERROR));

        return $alert;
    }

    /**
     * Resolve the threshold rule-set for a given metric name.
     *
     * Looks up Configure::read('Thresholds.<metricName>') first; when the key is
     * absent, falls back to DEFAULT_THRESHOLDS. Returns null when neither source
     * has rules for the requested metric.
     *
     * @param string $metricName The metric name to look up (e.g. 'cpu_usage').
     * @return list<array{threshold: float, severity: string, direction?: string}>|null Array of rules, or null if none.
     */
    private function resolveThresholds(string $metricName): ?array
    {
        /** @var list<array{threshold: float, severity: string, direction?: string}>|null $configured */
        $configured = Configure::read('Thresholds.' . $metricName);

        if ($configured !== null) {
            return $configured;
        }

        return self::DEFAULT_THRESHOLDS[$metricName] ?? null;
    }

    /**
     * Find the highest severity whose threshold the given value meets or exceeds.
     *
     * Iterates all rules and picks the one with the highest position in SEVERITY_ORDER.
     * Returns null when no rule is triggered (value is below every threshold).
     *
     * @param list<array{threshold: float, severity: string, direction?: string}> $rules Ordered set of threshold rules.
     * @param float $metricValue The measured metric value.
     * @return string|null The winning severity string, or null if no threshold is breached.
     */
    private function resolveHighestSeverity(array $rules, float $metricValue): ?string
    {
        $bestSeverity = null;
        $bestSeverityPos = -1;

        foreach ($rules as $rule) {
            if (!$this->isBreached($rule, $metricValue)) {
                continue;
            }

            $pos = array_search($rule['severity'], self::SEVERITY_ORDER, true);

            if ($pos !== false && $pos > $bestSeverityPos) {
                $bestSeverityPos = $pos;
                $bestSeverity = $rule['severity'];
            }
        }

        return $bestSeverity;
    }

    /**
     * Describe how the winning rule was breached, for use in the alert message.
     *
     * Returns "dropped below" when the rule that produced the winning severity
     * is a countdown rule, "exceeded" otherwise. When several rules of the same
     * severity are breached, the first match wins — mixing directions within a
     * single severity is not a meaningful configuration.
     *
     * @param list<array{threshold: float, severity: string, direction?: string}> $rules Rule-set under evaluation.
     * @param float $metricValue The measured metric value.
     * @param string $severity The severity that won evaluation.
     * @return string A verb phrase describing the breach.
     */
    private function breachVerb(array $rules, float $metricValue, string $severity): string
    {
        foreach ($rules as $rule) {
            if ($rule['severity'] !== $severity || !$this->isBreached($rule, $metricValue)) {
                continue;
            }

            if (($rule['direction'] ?? self::DEFAULT_DIRECTION) === self::DIRECTION_BELOW) {
                return 'dropped below';
            }

            return 'exceeded';
        }

        return 'exceeded';
    }

    /**
     * Decide whether a single rule is breached by the measured value.
     *
     * A rule without an explicit direction is treated as ABOVE, so rule-sets
     * written before direction support behave exactly as they did. An
     * unrecognised direction is also treated as ABOVE rather than throwing:
     * a typo in configuration must not silence a threshold, because a monitor
     * that fails quietly is worse than one that fires conservatively.
     *
     * @param array{threshold: float, severity: string, direction?: string} $rule The rule under test.
     * @param float $metricValue The measured metric value.
     * @return bool True when the rule is breached.
     */
    private function isBreached(array $rule, float $metricValue): bool
    {
        $threshold = (float)$rule['threshold'];
        $direction = $rule['direction'] ?? self::DEFAULT_DIRECTION;

        if ($direction === self::DIRECTION_BELOW) {
            return $metricValue <= $threshold;
        }

        return $metricValue >= $threshold;
    }
}
