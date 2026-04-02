<?php
declare(strict_types=1);

namespace App\Controller;

/**
 * DashboardController — main ops dashboard.
 *
 * Routes: GET /
 *
 * @skeleton M0
 * TODO (Planner): inject MetricsTable, AlertsTable; pass summary data to view.
 */
class DashboardController extends AppController
{
    public function index(): void
    {
        // TODO (Planner): load recent metrics and open alerts.
        $this->set('title', 'SDI Ops Monitor');
    }
}
