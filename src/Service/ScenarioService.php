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
 * scenario-1: CPU Spike — SDI Batch Processing (Milan) → 2 alerts
 * scenario-2: Memory Pressure — FatturaPA Validation (Rome) → 1 alert
 * scenario-3: Normal Operation — All Clear (Turin) → 0 alerts
 * scenario-4: FatturaPA Batch Failure Spike (Naples) → 3 alerts
 *
 * SDI context: the Sistema di Interscambio (SDI) is the Italian Revenue Agency
 * platform for electronic invoicing (FatturaPA). Rejection codes are five
 * digits and are returned inside a Notifica di Scarto (NS). The codes used in
 * the `sdi_error` tag below are taken from the official control list published
 * by the Agenzia delle Entrate (Elenco dei controlli, v1.7):
 *
 * 00002 — nome file duplicato: a file with this name was already transmitted
 * 00100 — certificato di firma scaduto
 * 00102 — file non integro: the digital signature does not verify
 * 00200 — file non conforme al formato: the XML fails the FatturaPA schema
 *
 * A successful ingestion produces no rejection code at all — it produces a
 * Ricevuta di Consegna (RC). Scenarios that model healthy traffic therefore
 * carry a null `sdi_error`, not a code.
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
     * Each entry carries both 'expected_outcome', prose for the operator, and
     * 'expected_alerts', the same claim as an integer. The duplication is
     * deliberate: prose drifts away from behaviour silently, an integer is
     * asserted by ScenarioServiceTest and cannot.
     *
     * The catalogue covers one failure mode per scenario rather than a set of
     * arbitrary spikes, because the point of the simulator is to demonstrate
     * that the system can tell these modes apart. Scenarios 1 and 2 are the pair
     * worth running back to back: near-identical service metrics, opposite
     * causes, opposite remedies.
     *
     * Threshold alignment (from AlertsService::DEFAULT_THRESHOLDS):
     * sdi_receipt_lag_minutes:  high ≥ 30,  critical ≥ 120
     * sdi_rejection_rate:       high ≥ 5 %, critical ≥ 15 %
     * invoices_pending:         high ≥ 500, critical ≥ 2000
     * signing_cert_expiry_days: high ≤ 30,  critical ≤ 7   (countdown)
     * cpu_usage:                high ≥ 80 %, critical ≥ 95 %
     * memory_usage:             high ≥ 85 %, critical ≥ 95 %
     *
     * @var array<string, array<string, mixed>>
     */
    private const SCENARIOS = [
        'scenario-1' => [
            'id' => 'scenario-1',
            'name' => 'Stalled channel — no receipts coming back',
            'description' => 'Invoices leave the Milan batch node normally and the SDI is not refusing '
                                . 'them, but receipts stop arriving: the lag climbs from 4 to 95 minutes '
                                . 'while the rejection rate stays at baseline and infrastructure is idle. '
                                . 'This is the failure that is easiest to misdiagnose, because the obvious '
                                . 'reflex — re-transmitting the batch — is the one thing that cannot help: '
                                . 'the files were accepted, it is the answers that are missing.',
            'expected_outcome' => '3 alerts (lag high, lag critical, pending critical); diagnosis: stalled queue',
            'expected_alerts' => 3,
            'source' => 'sdi-batch-milano-01',
            'tags' => ['sdi_error' => null, 'env' => 'prod', 'region' => 'eu-west-1', 'site' => 'CED Milano'],
            'events' => [
                ['name' => 'sdi_rejection_rate', 'value' => 1.1, 'unit' => 'percent'],
                ['name' => 'cpu_usage', 'value' => 34.0, 'unit' => 'percent'],
                ['name' => 'sdi_receipt_lag_minutes', 'value' => 4.0, 'unit' => 'minutes'],
                ['name' => 'sdi_receipt_lag_minutes', 'value' => 45.0, 'unit' => 'minutes'],
                ['name' => 'sdi_receipt_lag_minutes', 'value' => 95.0, 'unit' => 'minutes'],
                ['name' => 'invoices_pending', 'value' => 8266.0, 'unit' => 'count'],
            ],
        ],
        'scenario-2' => [
            'id' => 'scenario-2',
            'name' => 'Saturated nodes — lag caused from inside',
            'description' => 'The same service-level symptom as scenario 1 — receipts lagging, nothing '
                                . 'being refused — but here the Rome validator is saturated at 97 % CPU '
                                . 'and 94 % memory. Identical dashboard state, opposite cause and opposite '
                                . 'remedy: the bottleneck is this system failing to process the receipts '
                                . 'it receives, not the SDI failing to send them. Run it back to back with '
                                . 'scenario 1 to see the diagnosis change while the numbers stay similar.',
            'expected_outcome' => '4 alerts (lag, cpu high+critical, memory critical); '
                                . 'diagnosis: saturation is the cause',
            'expected_alerts' => 4,
            'source' => 'fatturapa-validator-roma-01',
            'tags' => ['sdi_error' => null, 'env' => 'prod', 'region' => 'eu-south-1', 'site' => 'CED Roma'],
            'events' => [
                ['name' => 'sdi_rejection_rate', 'value' => 0.9, 'unit' => 'percent'],
                ['name' => 'sdi_receipt_lag_minutes', 'value' => 88.0, 'unit' => 'minutes'],
                ['name' => 'cpu_usage', 'value' => 82.0, 'unit' => 'percent'],
                ['name' => 'cpu_usage', 'value' => 97.0, 'unit' => 'percent'],
                ['name' => 'memory_usage', 'value' => 94.0, 'unit' => 'percent'],
            ],
        ],
        'scenario-3' => [
            'id' => 'scenario-3',
            'name' => 'Normal operation — all clear',
            'description' => 'Nominal traffic through the Turin gateway during off-peak hours. Receipts '
                                . 'return within minutes, the rejection rate sits at its irreducible '
                                . 'baseline, and the signing certificate has months of validity left. '
                                . 'Useful for verifying that a healthy flow raises no spurious alerts — '
                                . 'in particular that a certificate valid for 210 more days is not read '
                                . 'as a breach, which is what a naive upward comparison would do.',
            'expected_outcome' => '0 alerts — system green',
            'expected_alerts' => 0,
            'source' => 'sdi-gateway-torino-01',
            'tags' => ['sdi_error' => null, 'env' => 'prod', 'region' => 'eu-west-1', 'site' => 'CED Torino'],
            'events' => [
                ['name' => 'sdi_receipt_lag_minutes', 'value' => 3.0, 'unit' => 'minutes'],
                ['name' => 'sdi_rejection_rate', 'value' => 0.8, 'unit' => 'percent'],
                ['name' => 'invoices_pending', 'value' => 52.0, 'unit' => 'count'],
                ['name' => 'signing_cert_expiry_days', 'value' => 210.0, 'unit' => 'days'],
                ['name' => 'cpu_usage', 'value' => 31.0, 'unit' => 'percent'],
                ['name' => 'memory_usage', 'value' => 48.0, 'unit' => 'percent'],
            ],
        ],
        'scenario-4' => [
            'id' => 'scenario-4',
            'name' => 'Expired signing certificate — SDI 00100',
            'description' => 'The signing certificate on the Naples batch node lapses and the SDI begins '
                                . 'refusing every file with code 00100 (certificato di firma scaduto). '
                                . 'Note the shape of the failure: the rejection rate goes to 98 % in one '
                                . 'step rather than drifting upwards, because a single cause is affecting '
                                . 'the whole batch. Infrastructure stays healthy throughout — nothing is '
                                . 'overloaded, everything is simply being turned away at the door.',
            'expected_outcome' => '2 alerts (rejection critical, cert critical); diagnosis names code 00100',
            'expected_alerts' => 2,
            'source' => 'sdi-batch-napoli-01',
            'tags' => ['sdi_error' => '00100', 'env' => 'prod', 'region' => 'eu-south-1', 'site' => 'CED Napoli'],
            'events' => [
                ['name' => 'sdi_receipt_lag_minutes', 'value' => 6.0, 'unit' => 'minutes'],
                ['name' => 'cpu_usage', 'value' => 22.0, 'unit' => 'percent'],
                ['name' => 'signing_cert_expiry_days', 'value' => 0.0, 'unit' => 'days'],
                ['name' => 'sdi_rejection_rate', 'value' => 98.0, 'unit' => 'percent'],
            ],
        ],
        'scenario-5' => [
            'id' => 'scenario-5',
            'name' => 'Duplicate file names on re-transmission — SDI 00002',
            'description' => 'A rejected batch is re-sent from Bologna without renaming the files, so the '
                                . 'SDI refuses it again with code 00002 (nome file duplicato). The '
                                . 'certificate is valid and the channel is answering promptly, which is '
                                . 'what separates this from scenario 4: the transmission mechanism works, '
                                . 'the payload is being turned away. Worth knowing that 00002 is about the '
                                . 'file name and not the invoice number — a rejected invoice may keep its '
                                . 'number, but the file must be renamed.',
            'expected_outcome' => '1 alert (rejection critical); diagnosis points at the payload, not the certificate',
            'expected_alerts' => 1,
            'source' => 'sdi-batch-bologna-01',
            'tags' => ['sdi_error' => '00002', 'env' => 'prod', 'region' => 'eu-south-1', 'site' => 'CED Bologna'],
            'events' => [
                ['name' => 'signing_cert_expiry_days', 'value' => 240.0, 'unit' => 'days'],
                ['name' => 'sdi_receipt_lag_minutes', 'value' => 5.0, 'unit' => 'minutes'],
                ['name' => 'sdi_rejection_rate', 'value' => 87.0, 'unit' => 'percent'],
            ],
        ],
        'scenario-6' => [
            'id' => 'scenario-6',
            'name' => 'Certificate expiring — warning before the outage',
            'description' => 'Every service metric on the Milan gateway is healthy, and the system alerts '
                                . 'anyway: the signing certificate expires in five days. This is the only '
                                . 'scenario in the catalogue where nothing has broken yet, and the only one '
                                . 'where acting on the alert prevents an incident rather than shortening '
                                . 'one. When the certificate lapses there is no gradual degradation to '
                                . 'notice — every transmission is refused at once with code 00100.',
            'expected_outcome' => '1 alert (cert critical) on an otherwise green system; diagnosis: start renewal now',
            'expected_alerts' => 1,
            'source' => 'sdi-gateway-milano-02',
            'tags' => ['sdi_error' => null, 'env' => 'prod', 'region' => 'eu-west-1', 'site' => 'CED Milano'],
            'events' => [
                ['name' => 'sdi_receipt_lag_minutes', 'value' => 3.0, 'unit' => 'minutes'],
                ['name' => 'sdi_rejection_rate', 'value' => 0.7, 'unit' => 'percent'],
                ['name' => 'invoices_pending', 'value' => 61.0, 'unit' => 'count'],
                ['name' => 'cpu_usage', 'value' => 29.0, 'unit' => 'percent'],
                ['name' => 'signing_cert_expiry_days', 'value' => 5.0, 'unit' => 'days'],
            ],
        ],
    ];

    private MetricsTable $metricsTable;
    private AlertsTable $alertsTable;

    /**
     * @param \App\Model\Table\MetricsTable $metricsTable Persistence layer for Metric entities.
     * @param \App\Model\Table\AlertsTable $alertsTable Persistence layer for Alert entities.
     */
    public function __construct(MetricsTable $metricsTable, AlertsTable $alertsTable)
    {
        $this->metricsTable = $metricsTable;
        $this->alertsTable = $alertsTable;
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
     * 1. A Metric entity is built and saved via MetricsTable (skipped in dry-run).
     * 2. AlertsService::evaluate() checks the saved metric against thresholds
     * and creates an Alert entity when a breach is detected (skipped in dry-run).
     *
     * Failures on individual metric saves are logged and skipped so that a single
     * bad record does not abort the entire scenario run.
     *
     * @param string $scenarioId The key from the scenario catalogue (e.g. 'scenario-1').
     * @param bool $dryRun When true, metrics and alerts are not persisted.
     * @return \App\Model\ScenarioResult The full outcome of the scenario run.
     * @throws \InvalidArgumentException When $scenarioId is not in the catalogue.
     */
    public function run(string $scenarioId, bool $dryRun = false): ScenarioResult
    {
        if (!isset(self::SCENARIOS[$scenarioId])) {
            throw new InvalidArgumentException(
                sprintf(
                    'Unknown scenario ID: "%s". Valid IDs: %s.',
                    $scenarioId,
                    implode(', ', array_keys(self::SCENARIOS)),
                ),
            );
        }

        $scenario = self::SCENARIOS[$scenarioId];
        $correlationId = $this->generateCorrelationId();
        $metricsInserted = [];
        $alertsCreated = [];
        $log = [];
        $eventCount = count($scenario['events']);

        // AlertsService is created once per run so all alerts share the same tables.
        $alertsService = new AlertsService($this->alertsTable);

        foreach ($scenario['events'] as $index => $event) {
            // Stagger recorded_at timestamps so entries appear in insertion order
            // in the Log Viewer (newest entry = last in the sequence).
            $secondsAgo = ($eventCount - $index) * 30;
            $recordedAt = (new DateTimeImmutable(sprintf('-%d seconds', $secondsAgo)))->format('Y-m-d H:i:s');

            $metricData = [
                'source' => $scenario['source'],
                'name' => $event['name'],
                'value' => $event['value'],
                'unit' => $event['unit'],
                'tags' => $scenario['tags'],
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
                    'timestamp' => date('c'),
                    'level' => 'error',
                    'correlation_id' => $correlationId,
                    'message' => 'ScenarioService: MetricsTable::save() failed.',
                    'context' => [
                        'scenario_id' => $scenarioId,
                        'metric_name' => $event['name'],
                        'errors' => $metric->getErrors(),
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
                        'id' => $alert->id,
                        'severity' => (string)$alert->severity,
                        'message' => (string)$alert->message,
                        'status' => (string)$alert->status,
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
                    'timestamp' => date('c'),
                    'level' => 'warning',
                    'correlation_id' => $correlationId,
                    'message' => 'ScenarioService: AlertsService::evaluate() threw an exception.',
                    'context' => [
                        'scenario_id' => $scenarioId,
                        'metric_name' => $event['name'],
                        'exception' => $e->getMessage(),
                    ],
                ], JSON_THROW_ON_ERROR));
            }
        }

        Log::info(json_encode([
            'timestamp' => date('c'),
            'level' => 'info',
            'correlation_id' => $correlationId,
            'message' => 'Scenario simulation completed.',
            'context' => [
                'scenario_id' => $scenarioId,
                'scenario_name' => $scenario['name'],
                'dry_run' => $dryRun,
                'metrics_inserted' => count($metricsInserted),
                'alerts_created' => count($alertsCreated),
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
