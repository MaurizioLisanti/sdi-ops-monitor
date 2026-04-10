<?php
declare(strict_types=1);

namespace App\Model;

/**
 * ScenarioResult — immutable value object returned by ScenarioService::run().
 *
 * Carries the outcome of a single scenario execution: the metric events that
 * were processed, the alerts that were generated, a per-run correlation ID for
 * log traceability, and an execution log for display in the UI.
 *
 * Extracted from App\Service\ScenarioService into App\Model because it is a
 * domain value object with no dependency on the service layer. ScenarioService
 * imports it via `use App\Model\ScenarioResult`.
 */
final class ScenarioResult
{
    /**
     * @param string                      $correlationId   UUID v4 generated for this run — searchable in Log Viewer.
     * @param string                      $scenarioName    Human-readable name of the executed scenario.
     * @param array<array<string, mixed>> $metricsInserted Metric data arrays that were processed (or would be in dry-run).
     * @param array<array<string, mixed>> $alertsCreated   Alert data arrays created by AlertsService during the run.
     * @param array<string>               $log             Ordered list of operation messages for the results UI.
     * @param bool                        $dryRun          True when no data was persisted to the database.
     */
    public function __construct(
        public readonly string $correlationId,
        public readonly string $scenarioName,
        public readonly array $metricsInserted,
        public readonly array $alertsCreated,
        public readonly array $log,
        public readonly bool $dryRun,
    ) {
    }
}
