<?php
/**
 * Dashboard/index — operational overview.
 *
 * View variables injected by DashboardController::index():
 *
 * @var \App\View\AppView        $this
 * @var int                      $metricsCount     Number of metric events recorded in the last 24 hours.
 * @var array                    $openAlerts       Open alerts (Alert entities) sorted by severity DESC.
 * @var string                   $overallStatus    Traffic-light status: 'green' | 'yellow' | 'red'.
 * @var string|null              $highestSeverity  Highest severity among open alerts, or null when none.
 * @var array<string,int>        $alertsBySeverity Per-level counts: ['critical'=>int,'high'=>int,...].
 */

// Map overall status to Bootstrap badge CSS classes and display labels.
$statusConfig = [
    'green'  => ['badge' => 'bg-success',          'label' => 'OK'],
    'yellow' => ['badge' => 'bg-warning text-dark', 'label' => 'WARNING'],
    'red'    => ['badge' => 'bg-danger',            'label' => 'CRITICAL'],
];
$statusCss   = $statusConfig[$overallStatus]['badge'] ?? 'bg-secondary';
$statusLabel = $statusConfig[$overallStatus]['label'] ?? 'UNKNOWN';

// Map alert severity to Bootstrap badge CSS classes.
$severityCss = [
    'critical' => 'bg-danger',
    'high'     => 'bg-warning text-dark',
    'medium'   => 'bg-info text-dark',
    'low'      => 'bg-secondary',
];

$alertCount = count($openAlerts);

// Derive count badge colour from severity-based overallStatus, not raw count.
// A single critical alert must show red even if the total count is 1.
if ($overallStatus === 'red') {
    $alertCountCss = 'text-danger fw-bold';
} elseif ($overallStatus === 'yellow') {
    $alertCountCss = 'text-warning fw-bold';
} else {
    $alertCountCss = 'text-success fw-bold';
}
?>
<?php $this->assign('title', 'Dashboard') ?>

<div class="container-fluid py-4">

    <!-- ── Page header ── -->
    <div class="d-flex align-items-center gap-3 mb-4">
        <h1 class="h3 mb-0">SDI Ops Monitor</h1>
        <span class="badge fs-6 <?= h($statusCss) ?>" title="Overall system status">
            <?= h($statusLabel) ?>
        </span>
    </div>

    <!-- ── Summary cards ── -->
    <div class="row g-3 mb-4">

        <!-- Metrics last 24 h -->
        <div class="col-sm-6 col-lg-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted text-uppercase small">
                        Metrics (last 24 h)
                    </h6>
                    <p class="display-5 fw-bold mb-0 text-primary">
                        <?= h((string)$metricsCount) ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Open alerts count -->
        <div class="col-sm-6 col-lg-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted text-uppercase small">
                        Open Alerts
                    </h6>
                    <p class="display-5 mb-0 <?= h($alertCountCss) ?>">
                        <?= h((string)$alertCount) ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Overall status traffic light -->
        <div class="col-sm-6 col-lg-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body d-flex flex-column justify-content-center">
                    <h6 class="card-subtitle mb-3 text-muted text-uppercase small">
                        Overall Status
                    </h6>
                    <!-- Traffic-light indicator: large coloured badge -->
                    <span class="badge fs-4 py-2 px-4 <?= h($statusCss) ?>"
                          aria-label="Overall system status: <?= h($statusLabel) ?>">
                        <?= h($statusLabel) ?>
                    </span>
                </div>
            </div>
        </div>

    </div>
    <!-- /.row summary cards -->

    <!-- ── Severity breakdown (hidden when no open alerts) ── -->
    <?php if ($alertCount > 0): ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header d-flex align-items-center gap-2">
            <strong>Severity Breakdown</strong>
            <span class="badge bg-secondary"><?= h((string)$alertCount) ?> total</span>
        </div>
        <div class="card-body py-3">
            <div class="d-flex flex-wrap gap-3">
                <?php foreach (['critical', 'high', 'medium', 'low'] as $level): ?>
                    <?php if ($alertsBySeverity[$level] > 0): ?>
                    <div class="d-flex align-items-center gap-2 rounded border px-3 py-2">
                        <span class="badge fs-5 <?= h($severityCss[$level] ?? 'bg-secondary') ?>">
                            <?= h((string)$alertsBySeverity[$level]) ?>
                        </span>
                        <span class="fw-semibold"><?= h(ucfirst($level)) ?></span>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <!-- /.severity breakdown -->

    <!-- ── Open alerts table ── -->
    <div class="card shadow-sm">
        <div class="card-header d-flex align-items-center gap-2">
            <strong>Open Alerts</strong>
            <?php if ($alertCount > 0): ?>
                <span class="badge <?= h($statusCss) ?>"><?= h((string)$alertCount) ?></span>
            <?php endif; ?>
        </div>

        <?php if (empty($openAlerts)): ?>
            <div class="card-body text-muted">
                <span class="text-success fw-semibold">&#10003;</span>
                No open alerts — system is healthy.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th scope="col">#</th>
                            <th scope="col">Severity</th>
                            <th scope="col">Message</th>
                            <th scope="col">Created</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($openAlerts as $alert): ?>
                        <tr>
                            <td class="text-muted small"><?= h((string)$alert->id) ?></td>
                            <td>
                                <span class="badge <?= h($severityCss[$alert->severity] ?? 'bg-secondary') ?>">
                                    <?= h((string)$alert->severity) ?>
                                </span>
                            </td>
                            <td><?= h((string)$alert->message) ?></td>
                            <td class="text-muted small text-nowrap">
                                <?= h($alert->created->format('Y-m-d H:i:s')) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <!-- /.card open alerts -->

</div>
<!-- /.container-fluid -->
