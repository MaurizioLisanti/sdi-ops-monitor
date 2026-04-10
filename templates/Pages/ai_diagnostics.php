<?php
/**
 * Pages/ai_diagnostics — AI Diagnostics page.
 *
 * Renders the system health diagnosis produced by AiDiagnosticsService.
 * The source badge indicates whether the diagnosis came from an LLM (AI)
 * or the built-in rule engine (Deterministic Fallback).
 *
 * View variables injected by AiDiagnosticsController::index():
 *
 * @var \App\View\AppView         $this
 * @var \App\Service\DiagnosisResult $diagnosis Diagnosis result object.
 */
?>
<?php $this->assign('title', 'AI Diagnostics') ?>

<div class="container-fluid py-4">

    <!-- ── Page header ── -->
    <div class="d-flex align-items-center gap-3 mb-2">
        <h1 class="h3 mb-0">AI Diagnostics</h1>
        <?php if ($diagnosis->source === 'ai'): ?>
            <span class="badge bg-primary">AI &middot; <?= h($diagnosis->model) ?></span>
        <?php else: ?>
            <span class="badge bg-secondary">Deterministic Fallback</span>
        <?php endif; ?>
    </div>
    <p class="text-muted mb-4">
        Automated health analysis based on the last
        <strong><?= h($diagnosis->metricsCount) ?></strong> metric event(s) and
        <strong><?= h($diagnosis->alertsCount) ?></strong> open alert(s).
    </p>

    <!-- ── Diagnosis card ── -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <span class="fw-semibold">Diagnosis</span>
            <small class="text-white-50"><?= h($diagnosis->generatedAt) ?></small>
        </div>
        <div class="card-body">
            <p class="mb-0 fs-5"><?= h($diagnosis->diagnosis) ?></p>
        </div>
    </div>

    <!-- ── Stats row ── -->
    <div class="row g-3 mb-4">
        <div class="col-sm-4">
            <div class="card border-0 bg-light text-center">
                <div class="card-body py-3">
                    <div class="h4 mb-0"><?= h($diagnosis->metricsCount) ?></div>
                    <small class="text-muted">Metrics analysed</small>
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="card border-0 bg-light text-center">
                <div class="card-body py-3">
                    <div class="h4 mb-0"><?= h($diagnosis->alertsCount) ?></div>
                    <small class="text-muted">Open alerts</small>
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="card border-0 bg-light text-center">
                <div class="card-body py-3">
                    <?php if ($diagnosis->source === 'ai'): ?>
                        <div class="h4 mb-0 text-primary">AI</div>
                    <?php else: ?>
                        <div class="h4 mb-0 text-secondary">Fallback</div>
                    <?php endif; ?>
                    <small class="text-muted">Source</small>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Traceability footer ── -->
    <p class="text-muted small mb-0">
        <strong>Correlation ID:</strong> <code><?= h($diagnosis->correlationId) ?></code>
    </p>

</div>
