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
     *   - $metricsCount  — number of metric events recorded in the last 24 hours
     *   - $openAlerts    — array of Alert entities with status = 'open', sorted by severity DESC
     *   - $overallStatus — traffic-light string: 'green' | 'yellow' | 'red'
     *
     * Traffic-light rules:
     *   green  : 0 open alerts
     *   yellow : 1–4 open alerts
     *   red    : ≥ 5 open alerts
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
        $alertCount = count($openAlerts);

        // Derive traffic-light status from open alert count.
        if ($alertCount === 0) {
            $overallStatus = 'green';
        } elseif ($alertCount < 5) {
            $overallStatus = 'yellow';
        } else {
            $overallStatus = 'red';
        }

        $this->set(compact('metricsCount', 'openAlerts', 'overallStatus'));
    }
}
