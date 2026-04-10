<?php
/**
 * ScenarioSimulator/index — SDI/FatturaPA scenario selection form.
 *
 * View variables injected by ScenarioSimulatorController::index() and run() (error path):
 *
 * @var \App\View\AppView          $this
 * @var array<string, array<string, mixed>> $scenarios  Scenario catalogue from ScenarioService.
 * @var string|null                $error      Validation error message, or null if no error.
 */

// Badge colours keyed by expected alert outcome suffix for visual affordance.
$outcomeBadge = [
    '0 alerts' => 'bg-success',
    '1 alert'  => 'bg-warning text-dark',
    '2 alerts' => 'bg-danger',
    '3 alerts' => 'bg-danger',
];

/**
 * Resolve the Bootstrap badge class for a scenario's expected outcome string.
 *
 * @param string $expected The expected_outcome string from the catalogue.
 * @param array<string, string> $map Badge class map.
 * @return string Bootstrap badge class.
 */
$outcomeClass = static function (string $expected, array $map): string {
    foreach ($map as $key => $class) {
        if (str_starts_with($expected, $key)) {
            return $class;
        }
    }
    return 'bg-secondary';
};
?>
<?php $this->assign('title', 'Scenario Simulator') ?>

<div class="container-fluid py-4">

    <!-- ── Page header ── -->
    <div class="d-flex align-items-center gap-3 mb-2">
        <h1 class="h3 mb-0">SDI / FatturaPA Scenario Simulator</h1>
        <span class="badge bg-secondary">Demo &amp; Regression</span>
    </div>
    <p class="text-muted mb-4">
        Select a predefined operational scenario to inject metric events directly
        into the pipeline (MetricsTable + AlertsService). Use <strong>Dry run</strong>
        to preview what would happen without writing to the database.
    </p>

    <!-- ── Validation error ── -->
    <?php if ($error !== null): ?>
        <div class="alert alert-danger d-flex align-items-center gap-2 mb-4" role="alert">
            <strong>Error:</strong> <?= h($error) ?>
        </div>
    <?php endif; ?>

    <form method="post" action="/simulate/run">

        <!-- CSRF token — required by CsrfProtectionMiddleware for all POST requests. -->
        <input type="hidden" name="_csrfToken"
               value="<?= h((string)($this->request->getAttribute('csrfToken') ?? '')) ?>">

        <!-- ── Scenario cards ── -->
        <div class="row g-4 mb-4">
            <?php foreach ($scenarios as $id => $scenario): ?>
            <?php $badgeClass = $outcomeClass($scenario['expected_outcome'], $outcomeBadge); ?>
            <div class="col-md-6">
                <div class="card h-100 shadow-sm scenario-card">
                    <div class="card-body">
                        <div class="form-check mb-2">
                            <input class="form-check-input"
                                   type="radio"
                                   name="scenario_id"
                                   id="<?= h($id) ?>"
                                   value="<?= h($id) ?>"
                                   required>
                            <label class="form-check-label fw-semibold fs-6" for="<?= h($id) ?>">
                                <?= h($scenario['name']) ?>
                            </label>
                        </div>
                        <p class="text-muted small mb-2"><?= h($scenario['description']) ?></p>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
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
                                SDI-<?= h($scenario['tags']['sdi_error']) ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-footer d-flex justify-content-between align-items-center">
                        <small class="text-muted">
                            <?= count($scenario['events']) ?> metric event<?= count($scenario['events']) !== 1 ? 's' : '' ?>
                        </small>
                        <span class="badge <?= h($badgeClass) ?>">
                            Expected: <?= h($scenario['expected_outcome']) ?>
                        </span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ── Run options ── -->
        <div class="card shadow-sm mb-4">
            <div class="card-header fw-semibold">Run Options</div>
            <div class="card-body">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox"
                           name="dry_run" value="1" id="dry-run">
                    <label class="form-check-label" for="dry-run">
                        <strong>Dry run</strong> — simulate without writing to the database.
                        Use this in production environments to preview alert outcomes safely.
                    </label>
                </div>
            </div>
        </div>

        <!-- ── Submit ── -->
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary px-4">
                ▶ Run Scenario
            </button>
            <a href="/" class="btn btn-outline-secondary">View Dashboard</a>
            <a href="/logs" class="btn btn-outline-secondary">Log Viewer</a>
        </div>

    </form>

</div><!-- /.container-fluid -->

<style>
.scenario-card { transition: box-shadow 0.15s; cursor: pointer; }
.scenario-card:hover { box-shadow: 0 0 0 2px #0d6efd !important; }
.scenario-card:has(.form-check-input:checked) {
    box-shadow: 0 0 0 2px #0d6efd !important;
    border-color: #0d6efd;
}
</style>
