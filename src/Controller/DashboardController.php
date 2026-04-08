<?php
declare(strict_types=1);

namespace App\Controller;

/**
 * DashboardController — operational overview dashboard.
 *
 * Provides the main entry-point for the SDI Ops Monitor web UI.
 * Loads aggregated metrics count and open alerts from the database
 * and derives an overall traffic-light status.
 *
 * Routes:
 *   GET /  → index()
 */
class DashboardController extends AppController
{
    /**
     * Render the operations dashboard.
     *
     * Loads the following view variables:
     *   - $metricsCount     — number of metric events recorded in the last 24 hours
     *   - $openAlerts       — array of Alert entities with status = 'open', sorted by severity DESC
     *   - $overallStatus    — traffic-light string: 'green' | 'yellow' | 'red'
     *   - $highestSeverity  — highest severity among open alerts: 'critical'|'high'|'medium'|'low'|null
     *   - $alertsBySeverity — per-level count map: ['critical'=>int,'high'=>int,'medium'=>int,'low'=>int]
     *
     * Traffic-light rules (severity-based — A1):
     *   red    : at least 1 open alert with severity 'critical' or 'high'
     *   yellow : at least 1 open alert with severity 'medium' or 'low', none critical/high
     *   green  : 0 open alerts
     *
     * @return void CakePHP renders templates/Dashboard/index.php automatically.
     */
    public function index(): void
    {
        $metricsTable = $this->fetchTable('Metrics');
        $alertsTable  = $this->fetchTable('Alerts');

        // Count metric events recorded in the last 24 hours.
        $metricsCount = $metricsTable->find('recent24h')->count();

        // Load all open alerts; convert to array so the view can safely
        // iterate and count without risking a double-execution of the query.
        $openAlerts = $alertsTable->find('open')->all()->toArray();

        // Build per-severity counts in canonical order; unknown severity values
        // are ignored defensively — the validator enforces the allowed list on write.
        $alertsBySeverity = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
        foreach ($openAlerts as $alert) {
            $sev = (string)($alert->severity ?? '');
            if (array_key_exists($sev, $alertsBySeverity)) {
                $alertsBySeverity[$sev]++;
            }
        }

        // Walk severity levels in descending order to find the highest one present.
        $highestSeverity = null;
        foreach (['critical', 'high', 'medium', 'low'] as $level) {
            if ($alertsBySeverity[$level] > 0) {
                $highestSeverity = $level;
                break;
            }
        }

        // Severity-based traffic-light: reflects risk level, not raw alert count.
        // red    = any critical/high alert demands immediate action.
        // yellow = only medium/low alerts present, attention needed.
        // green  = no open alerts.
        if ($alertsBySeverity['critical'] > 0 || $alertsBySeverity['high'] > 0) {
            $overallStatus = 'red';
        } elseif ($alertsBySeverity['medium'] > 0 || $alertsBySeverity['low'] > 0) {
            $overallStatus = 'yellow';
        } else {
            $overallStatus = 'green';
        }

        $this->set(compact('metricsCount', 'openAlerts', 'overallStatus', 'alertsBySeverity', 'highestSeverity'));
    }
}
