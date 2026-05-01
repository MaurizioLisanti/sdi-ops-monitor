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
 * 'cpu_usage' => [
 * ['threshold' => 80.0, 'severity' => 'high'],
 * ['threshold' => 95.0, 'severity' => 'critical'],
 * ],
 * ],
 * ```
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
     * Built-in fallback thresholds used when Configure does not define the metric.
     * Each entry is an array of rules: ['threshold' => float, 'severity' => string].
     * Rules are evaluated independently; the highest triggered severity wins.
     *
     * @var array<string, list<array{threshold: float, severity: string}>>
     */
    private const DEFAULT_THRESHOLDS = [
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

        // Build the alert message including which threshold was breached.
        $message = sprintf(
            "Metric '%s' value %.4g exceeded %s threshold.",
            $metricName,
            $metricValue,
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
     * @return list<array{threshold: float, severity: string}>|null Array of rules, or null if none.
     */
    private function resolveThresholds(string $metricName): ?array
    {
        /** @var list<array{threshold: float, severity: string}>|null $configured */
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
     * @param list<array{threshold: float, severity: string}> $rules Ordered set of threshold rules.
     * @param float $metricValue The measured metric value.
     * @return string|null The winning severity string, or null if no threshold is breached.
     */
    private function resolveHighestSeverity(array $rules, float $metricValue): ?string
    {
        $bestSeverity = null;
        $bestSeverityPos = -1;

        foreach ($rules as $rule) {
            if ($metricValue >= (float)$rule['threshold']) {
                $pos = array_search($rule['severity'], self::SEVERITY_ORDER, true);

                if ($pos !== false && $pos > $bestSeverityPos) {
                    $bestSeverityPos = $pos;
                    $bestSeverity = $rule['severity'];
                }
            }
        }

        return $bestSeverity;
    }
}
