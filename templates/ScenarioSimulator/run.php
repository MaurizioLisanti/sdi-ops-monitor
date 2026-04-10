<?php
/**
 * ScenarioSimulator/run — scenario execution results.
 *
 * View variables injected by ScenarioSimulatorController::run():
 *
 * @var \App\View\AppView    $this
 * @var \App\Service\ScenarioResult        $result   Outcome of the scenario run.
 * @var array<string, mixed>               $scenario Matching catalogue entry for context.
 */

// Bootstrap 5 badge classes keyed by alert severity.
$severityBadge = [
    'critical' => 'bg-danger',
    'high'     => 'bg-warning text-dark',
    'medium'   => 'bg-info text-dark',
    'low'      => 'bg-secondary',
];

// Log line prefix → Bootstrap text colour for the execution log panel.
$logLineClass = static function (string $line): string {
    if (str_starts_with($line, '[ALERT]')) {
        return 'text-danger';
    }
    if (str_starts_with($line, '[ERROR]') || str_starts_with($line, '[WARNING]')) {
        return 'text-warning';
    }
    if (str_starts_with($line, '[DRY-RUN]')) {
        return 'text-info';
    }
    return 'text-light';
};
?>
<?php $this->assign('title', 'Scenario Result') ?>

<div class="container-fluid py-4">

    <!-- ── DRY RUN banner ── -->
    <?php if ($result->dryRun): ?>
        <div class="alert alert-warning d-flex align-items-center gap-2 mb-4" role="alert">
            <strong>DRY RUN — no data persisted.</strong>
            The table below shows what <em>would</em> have been inserted if dry run were disabled.
        </div>
    <?php endif; ?>

    <!-- ── Page header ── -->
    <div class="d-flex align-items-center gap-3 mb-1 flex-wrap">
        <h1 class="h3 mb-0">Scenario Result</h1>
        <?php if ($result->dryRun): ?>
            <span class="badge bg-warning text-dark">DRY RUN</span>
        <?php else: ?>
            <span class="badge bg-success">EXECUTED</span>
        <?php endif; ?>
    </div>
    <p class="text-muted mb-1"><?= h($result->scenarioName) ?></p>

    <!-- ── Correlation ID ── -->
    <div class="mb-4 p-3 bg-light border rounded d-flex align-items-center gap-3 flex-wrap">
        <span class="fw-semibold text-muted small text-uppercase">Run Correlation ID</span>
        <code class="fs-6 text-primary"><?= h($result->correlationId) ?></code>
        <a href="/logs?correlation_id=<?= h($result->correlationId) ?>"
           class="btn btn-outline-secondary btn-sm">
            Search in Log Viewer →
        </a>
    </div>

    <div class="row g-4 mb-4">

        <!-- ── Metrics processed ── -->
        <div class="col-lg-7">
            <div class="card shadow-sm h-100">
                <div class="card-header d-flex align-items-center gap-2">
                    <strong>Metrics <?= $result->dryRun ? 'Simulated' : 'Inserted' ?></strong>
                    <span class="badge bg-secondary"><?= h((string)count($result->metricsInserted)) ?></span>
                </div>
                <?php if (empty($result->metricsInserted)): ?>
                    <div class="card-body text-muted fst-italic">No metrics were processed.</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0 font-monospace"
                           style="font-size: 0.82rem;">
                        <thead class="table-dark">
                            <tr>
                                <?php if (!$result->dryRun): ?>
                                <th scope="col">ID</th>
                                <?php endif; ?>
                                <th scope="col">Source</th>
                                <th scope="col">Metric</th>
                                <th scope="col" class="text-end">Value</th>
                                <th scope="col">Timestamp</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($result->metricsInserted as $metric): ?>
                            <tr>
                                <?php if (!$result->dryRun && isset($metric['id'])): ?>
                                <td class="text-muted"><?= h((string)$metric['id']) ?></td>
                                <?php endif; ?>
                                <td><?= h($metric['source']) ?></td>
                                <td><?= h($metric['name']) ?></td>
                                <td class="text-end fw-semibold">
                                    <?= h(number_format((float)$metric['value'], 1)) ?>&nbsp;%
                                </td>
                                <td class="text-muted small text-nowrap">
                                    <?= h($metric['recorded_at']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── Alerts created ── -->
        <div class="col-lg-5">
            <div class="card shadow-sm h-100">
                <div class="card-header d-flex align-items-center gap-2">
                    <strong>Alerts <?= $result->dryRun ? 'Simulated' : 'Created' ?></strong>
                    <?php if (count($result->alertsCreated) === 0): ?>
                        <span class="badge bg-success">0 — system green</span>
                    <?php else: ?>
                        <span class="badge bg-danger"><?= h((string)count($result->alertsCreated)) ?></span>
                    <?php endif; ?>
                </div>
                <?php if (empty($result->alertsCreated)): ?>
                    <div class="card-body">
                        <div class="alert alert-success mb-0">
                            All metric values are within healthy thresholds — no alerts generated.
                        </div>
                    </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0"
                           style="font-size: 0.84rem;">
                        <thead class="table-dark">
                            <tr>
                                <?php if (!$result->dryRun): ?>
                                <th scope="col">ID</th>
                                <?php endif; ?>
                                <th scope="col">Severity</th>
                                <th scope="col">Message</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($result->alertsCreated as $alert): ?>
                            <?php $badgeClass = $severityBadge[$alert['severity']] ?? 'bg-secondary'; ?>
                            <tr class="<?= in_array($alert['severity'], ['critical', 'high'], true) ? 'table-danger' : '' ?>">
                                <?php if (!$result->dryRun && isset($alert['id'])): ?>
                                <td class="text-muted"><?= h((string)$alert['id']) ?></td>
                                <?php endif; ?>
                                <td>
                                    <span class="badge <?= h($badgeClass) ?>">
                                        <?= h(strtoupper($alert['severity'])) ?>
                                    </span>
                                </td>
                                <td style="word-break: break-word;"><?= h($alert['message']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <!-- Expected outcome comparison -->
                <div class="card-footer text-muted small">
                    Expected: <em><?= h($scenario['expected_outcome']) ?></em>
                    &nbsp;·&nbsp;
                    Got: <strong><?= h((string)count($result->alertsCreated)) ?> alert<?= count($result->alertsCreated) !== 1 ? 's' : '' ?></strong>
                    <?php if (!$result->dryRun): ?>
                    &nbsp;
                    <?php $expectedCount = (int)preg_replace('/[^0-9]/', '', explode(' ', $scenario['expected_outcome'])[0]); ?>
                    <?php if ($expectedCount === count($result->alertsCreated)): ?>
                        <span class="text-success fw-semibold">✓ matches expected</span>
                    <?php else: ?>
                        <span class="text-danger fw-semibold">✗ mismatch</span>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>

    <!-- ── Execution log ── -->
    <div class="card shadow-sm mb-4">
        <div class="card-header fw-semibold">Execution Log</div>
        <div class="card-body p-0">
            <pre class="bg-dark text-light rounded-bottom mb-0 p-3"
                 style="font-size: 0.78rem; max-height: 260px; overflow-y: auto;"><?php foreach ($result->log as $line): ?><span class="<?= h($logLineClass($line)) ?>"><?= h($line) ?></span>
<?php endforeach; ?></pre>
        </div>
    </div>

    <!-- ── Source tags ── -->
    <div class="mb-4 d-flex gap-2 flex-wrap">
        <span class="badge bg-light text-dark border">
            Source: <code class="text-dark"><?= h($scenario['source']) ?></code>
        </span>
        <?php if (!empty($scenario['tags']['site'])): ?>
        <span class="badge bg-light text-dark border">
            📍 <?= h($scenario['tags']['site']) ?>
        </span>
        <?php endif; ?>
        <?php if (!empty($scenario['tags']['sdi_error'])): ?>
        <span class="badge bg-light text-dark border font-monospace">
            SDI error code: <?= h($scenario['tags']['sdi_error']) ?>
        </span>
        <?php endif; ?>
        <?php if (!empty($scenario['tags']['region'])): ?>
        <span class="badge bg-light text-dark border">
            Region: <?= h($scenario['tags']['region']) ?>
        </span>
        <?php endif; ?>
    </div>

    <!-- ── Navigation actions ── -->
    <div class="d-flex gap-2 flex-wrap">
        <a href="/simulate" class="btn btn-primary">← Run Again</a>
        <a href="/" class="btn btn-outline-secondary">View Dashboard</a>
        <a href="/logs?correlation_id=<?= h($result->correlationId) ?>"
           class="btn btn-outline-secondary">
            Search Logs for This Run
        </a>
    </div>

</div><!-- /.container-fluid -->
