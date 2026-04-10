<?php
declare(strict_types=1);

namespace App\Service;

use App\Model\ScenarioResult;
use App\Model\Table\AlertsTable;
use App\Model\Table\MetricsTable;
use Cake\Log\Log;
use DateTimeImmutable;
use InvalidArgumentException;
use Throwable;

/**
 * ScenarioService — SDI/FatturaPA operational scenario simulator.
 *
 * Provides a static catalogue of four predefined operational scenarios that
 * exercise the metric ingestion and alert evaluation pipeline without going
 * through HTTP. Each scenario injects Metric entities directly via MetricsTable
 * and evaluates alert thresholds via AlertsService.
 *
 * Scenario catalogue:
 *   scenario-1: CPU Spike — SDI Batch Processing (Milan)        → 2 alerts
 *   scenario-2: Memory Pressure — FatturaPA Validation (Rome)   → 1 alert
 *   scenario-3: Normal Operation — All Clear (Turin)            → 0 alerts
 *   scenario-4: FatturaPA Batch Failure Spike (Naples)          → 3 alerts
 *
 * SDI context: the Sistema di Interscambio (SDI) is the Italian Revenue Agency
 * platform for electronic invoicing (FatturaPA). Error codes used in tags:
 *   003 — file received and queued for processing
 *   004 — file rejected: signing certificate invalid or expired
 *   009 — file rejected: duplicate invoice identifier
 *
 * Security: scenario_id is validated against the static catalogue before any
 * database operation is performed. No user-supplied paths or SQL are accepted.
 */
class ScenarioService
{
    /**
     * Static scenario catalogue — never read from the database or from user input.
     *
     * Each scenario defines the SDI source system, the event sequence (metric name
     * and value), the Italian operational site, and the expected alert outcome so
     * operators can verify pipeline correctness at a glance.
     *
     * Threshold alignment (from AlertsService::DEFAULT_THRESHOLDS):
     *   cpu_usage:    high ≥ 80.0 %,  critical ≥ 95.0 %
     *   memory_usage: high ≥ 85.0 %,  critical ≥ 95.0 %
     *
     * @var array<string, array<string, mixed>>
     */
    private const SCENARIOS = [
        'scenario-1' => [
            'id'               => 'scenario-1',
            'name'             => 'CPU Spike — SDI Batch Processing',
            'description'      => 'Simulates a CPU saturation event on the Milan SDI batch processor '
                                . 'during peak FatturaPA invoice ingestion (SDI error code 003 — file received). '
                                . 'Three metric samples: 55 % (healthy), 82 % (high breach), 97 % (critical breach).',
            'expected_outcome' => '2 alerts: 1 high + 1 critical',
            'source'           => 'sdi-batch-milano-01',
            'tags'             => ['sdi_error' => '003', 'env' => 'prod', 'region' => 'eu-west-1', 'site' => 'CED Milano'],
            'events'           => [
                ['name' => 'cpu_usage', 'value' => 55.0, 'unit' => 'percent'],
                ['name' => 'cpu_usage', 'value' => 82.0, 'unit' => 'percent'],
                ['name' => 'cpu_usage', 'value' => 97.0, 'unit' => 'percent'],
            ],
        ],
        'scenario-2' => [
            'id'               => 'scenario-2',
            'name'             => 'Memory Pressure — FatturaPA Validation',
            'description'      => 'Simulates memory saturation on the Rome FatturaPA validator '
                                . 'during batch validation of large XML invoice packages '
                                . '(SDI error code 004 — invalid signing certificate). '
                                . 'Two samples: 70 % (healthy), 92 % (high breach).',
            'expected_outcome' => '1 alert: 1 high',
            'source'           => 'fatturapa-validator-roma-01',
            'tags'             => ['sdi_error' => '004', 'env' => 'prod', 'region' => 'eu-south-1', 'site' => 'CED Roma'],
            'events'           => [
                ['name' => 'memory_usage', 'value' => 70.0, 'unit' => 'percent'],
                ['name' => 'memory_usage', 'value' => 92.0, 'unit' => 'percent'],
            ],
        ],
        'scenario-3' => [
            'id'               => 'scenario-3',
            'name'             => 'Normal Operation — All Clear',
            'description'      => 'Simulates nominal load across the Turin SDI gateway '
                                . 'during off-peak hours. All three metric samples are '
                                . 'comfortably below alert thresholds. Useful for verifying '
                                . 'that healthy metrics do not trigger spurious alerts.',
            'expected_outcome' => '0 alerts — system green',
            'source'           => 'sdi-gateway-torino-01',
            'tags'             => ['sdi_error' => null, 'env' => 'prod', 'region' => 'eu-west-1', 'site' => 'CED Torino'],
            'events'           => [
                ['name' => 'cpu_usage',    'value' => 30.0, 'unit' => 'percent'],
                ['name' => 'memory_usage', 'value' => 50.0, 'unit' => 'percent'],
                ['name' => 'cpu_usage',    'value' => 40.0, 'unit' => 'percent'],
            ],
        ],
        'scenario-4' => [
            'id'               => 'scenario-4',
            'name'             => 'FatturaPA Batch Failure Spike — Naples',
            'description'      => 'Simulates a cascading failure on the Naples SDI batch node '
                                . 'caused by a flood of duplicate invoice rejections '
                                . '(SDI error code 009 — duplicate file identifier). '
                                . 'Four metric samples produce three alert breaches: '
                                . '65 % CPU (ok), 88 % memory (high), 96 % CPU (critical), '
                                . '97 % memory (critical).',
            'expected_outcome' => '3 alerts: 1 high + 2 critical',
            'source'           => 'sdi-batch-napoli-01',
            'tags'             => ['sdi_error' => '009', 'env' => 'prod', 'region' => 'eu-south-1', 'site' => 'CED Napoli'],
            'events'           => [
                ['name' => 'cpu_usage',    'value' => 65.0, 'unit' => 'percent'],
                ['name' => 'memory_usage', 'value' => 88.0, 'unit' => 'percent'],
                ['name' => 'cpu_usage',    'value' => 96.0, 'unit' => 'percent'],
                ['name' => 'memory_usage', 'value' => 97.0, 'unit' => 'percent'],
            ],
        ],
    ];

    private MetricsTable $metricsTable;
    private AlertsTable $alertsTable;

    /**
     * @param \App\Model\Table\MetricsTable $metricsTable Persistence layer for Metric entities.
     * @param \App\Model\Table\AlertsTable  $alertsTable  Persistence layer for Alert entities.
     */
    public function __construct(MetricsTable $metricsTable, AlertsTable $alertsTable)
    {
        $this->metricsTable = $metricsTable;
        $this->alertsTable  = $alertsTable;
    }

    /**
     * Return the full scenario catalogue for rendering in the selection UI.
     *
     * @return array<string, array<string, mixed>> Keyed by scenario ID.
     */
    public function getScenarios(): array
    {
        return self::SCENARIOS;
    }

    /**
     * Execute a named scenario against the live database or in dry-run mode.
     *
     * Each metric event in the scenario is processed in sequence:
     *   1. A Metric entity is built and saved via MetricsTable (skipped in dry-run).
     *   2. AlertsService::evaluate() checks the saved metric against thresholds
     *      and creates an Alert entity when a breach is detected (skipped in dry-run).
     *
     * Failures on individual metric saves are logged and skipped so that a single
     * bad record does not abort the entire scenario run.
     *
     * @param string $scenarioId The key from the scenario catalogue (e.g. 'scenario-1').
     * @param bool   $dryRun     When true, metrics and alerts are not persisted.
     * @return \App\Model\ScenarioResult The full outcome of the scenario run.
     * @throws \InvalidArgumentException When $scenarioId is not in the catalogue.
     */
    public function run(string $scenarioId, bool $dryRun = false): ScenarioResult
    {
        if (!isset(self::SCENARIOS[$scenarioId])) {
            throw new InvalidArgumentException(
                sprintf('Unknown scenario ID: "%s". Valid IDs: %s.', $scenarioId, implode(', ', array_keys(self::SCENARIOS))),
            );
        }

        $scenario      = self::SCENARIOS[$scenarioId];
        $correlationId = $this->generateCorrelationId();
        $metricsInserted = [];
        $alertsCreated   = [];
        $log             = [];
        $eventCount      = count($scenario['events']);

        // AlertsService is created once per run so all alerts share the same tables.
        $alertsService = new AlertsService($this->alertsTable);

        foreach ($scenario['events'] as $index => $event) {
            // Stagger recorded_at timestamps so entries appear in insertion order
            // in the Log Viewer (newest entry = last in the sequence).
            $secondsAgo = ($eventCount - $index) * 30;
            $recordedAt = (new DateTimeImmutable(sprintf('-%d seconds', $secondsAgo)))->format('Y-m-d H:i:s');

            $metricData = [
                'source'      => $scenario['source'],
                'name'        => $event['name'],
                'value'       => $event['value'],
                'unit'        => $event['unit'],
                'tags'        => $scenario['tags'],
                'recorded_at' => $recordedAt,
            ];

            if ($dryRun) {
                // In dry-run mode, record what would happen without touching the DB.
                $metricsInserted[] = $metricData;
                $log[] = sprintf(
                    '[DRY-RUN] Would insert metric: %s = %.4g %s from %s',
                    $event['name'],
                    $event['value'],
                    $event['unit'],
                    $scenario['source'],
                );
                continue;
            }

            $metric = $this->metricsTable->newEntity($metricData);

            if (!$this->metricsTable->save($metric)) {
                $log[] = sprintf('[ERROR] Failed to save metric: %s = %.4g', $event['name'], $event['value']);

                Log::error(json_encode([
                    'timestamp'      => date('c'),
                    'level'          => 'error',
                    'correlation_id' => $correlationId,
                    'message'        => 'ScenarioService: MetricsTable::save() failed.',
                    'context'        => [
                        'scenario_id' => $scenarioId,
                        'metric_name' => $event['name'],
                        'errors'      => $metric->getErrors(),
                    ],
                ], JSON_THROW_ON_ERROR));

                // Skip this event but continue with the remaining ones in the scenario.
                continue;
            }

            $metricsInserted[] = array_merge($metricData, ['id' => $metric->id]);
            $log[] = sprintf(
                '[OK] Metric id=%d inserted: %s = %.4g %s',
                (int)$metric->id,
                $event['name'],
                $event['value'],
                $event['unit'],
            );

            try {
                $alert = $alertsService->evaluate($metric, $correlationId);

                if ($alert !== null) {
                    $alertsCreated[] = [
                        'id'       => $alert->id,
                        'severity' => (string)$alert->severity,
                        'message'  => (string)$alert->message,
                        'status'   => (string)$alert->status,
                    ];

                    $log[] = sprintf(
                        '[ALERT] %s threshold breached — %s alert created (id=%d)',
                        strtoupper((string)$alert->severity),
                        $alert->severity,
                        (int)$alert->id,
                    );
                } else {
                    $log[] = sprintf('[OK] No threshold breached for %s = %.4g', $event['name'], $event['value']);
                }
            } catch (Throwable $e) {
                $log[] = sprintf('[WARNING] AlertsService failed for %s: %s', $event['name'], $e->getMessage());

                Log::warning(json_encode([
                    'timestamp'      => date('c'),
                    'level'          => 'warning',
                    'correlation_id' => $correlationId,
                    'message'        => 'ScenarioService: AlertsService::evaluate() threw an exception.',
                    'context'        => [
                        'scenario_id' => $scenarioId,
                        'metric_name' => $event['name'],
                        'exception'   => $e->getMessage(),
                    ],
                ], JSON_THROW_ON_ERROR));
            }
        }

        Log::info(json_encode([
            'timestamp'      => date('c'),
            'level'          => 'info',
            'correlation_id' => $correlationId,
            'message'        => 'Scenario simulation completed.',
            'context'        => [
                'scenario_id'      => $scenarioId,
                'scenario_name'    => $scenario['name'],
                'dry_run'          => $dryRun,
                'metrics_inserted' => count($metricsInserted),
                'alerts_created'   => count($alertsCreated),
            ],
        ], JSON_THROW_ON_ERROR));

        return new ScenarioResult(
            correlationId: $correlationId,
            scenarioName: $scenario['name'],
            metricsInserted: $metricsInserted,
            alertsCreated: $alertsCreated,
            log: $log,
            dryRun: $dryRun,
        );
    }

    /**
     * Generate a cryptographically random UUID v4 for this scenario run.
     *
     * Uses random_bytes() so the output is suitable for use as a correlation ID
     * in security-sensitive logging contexts (not mt_rand-based).
     *
     * @return string UUID v4 string in the standard 8-4-4-4-12 hex format.
     */
    private function generateCorrelationId(): string
    {
        $bytes = random_bytes(16);

        // Set version to 4 (bits 12–15 of time_hi_and_version).
        $bytes[6] = chr(ord($bytes[6]) & 0x0f | 0x40);

        // Set variant to RFC 4122 (bits 6–7 of clock_seq_hi_and_reserved).
        $bytes[8] = chr(ord($bytes[8]) & 0x3f | 0x80);

        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }
}
