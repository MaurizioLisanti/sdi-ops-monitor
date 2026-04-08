<?php
/**
 * LogViewer/index — structured JSON log viewer.
 *
 * View variables injected by LogViewerController::index():
 *
 * @var \App\View\AppView        $this
 * @var array<array<string,mixed>> $entries           Parsed log entry arrays (newest first).
 * @var bool                     $logFileFound      Whether logs/app.log exists.
 * @var int                      $lineCount         Number of tail lines requested.
 * @var string                   $levelFilter       Active level filter, or '' for none.
 * @var string                   $correlationFilter Active correlation ID filter, or ''.
 * @var array<string>            $validLevels       Accepted log level strings for the select.
 */

// Bootstrap 5 badge classes keyed by log level.
$levelBadge = [
    'error'    => 'bg-danger',
    'critical' => 'bg-danger',
    'warning'  => 'bg-warning text-dark',
    'info'     => 'bg-info text-dark',
    'debug'    => 'bg-secondary',
    'raw'      => 'bg-light text-dark border',
];
?>
<?php $this->assign('title', 'Log Viewer') ?>

<div class="container-fluid py-4">

    <!-- ── Page header ── -->
    <div class="d-flex align-items-center gap-3 mb-3">
        <h1 class="h3 mb-0">Log Viewer</h1>
        <small class="text-muted">
            <?php if ($logFileFound): ?>
                Showing last <?= h((string)$lineCount) ?> lines of
                <code>logs/app.log</code>
            <?php else: ?>
                <span class="text-warning">logs/app.log not found</span>
            <?php endif; ?>
        </small>
    </div>

    <!-- ── Filter form ── -->
    <div class="card shadow-sm mb-4">
        <div class="card-header fw-semibold">Filters</div>
        <div class="card-body">
            <form method="get" action="/logs" class="row g-3 align-items-end">

                <!-- Level filter -->
                <div class="col-sm-6 col-md-3">
                    <label for="filter-level" class="form-label small fw-semibold">Level</label>
                    <select id="filter-level" name="level" class="form-select form-select-sm">
                        <option value="">All levels</option>
                        <?php foreach ($validLevels as $lvl): ?>
                            <option value="<?= h($lvl) ?>"
                                <?= $levelFilter === $lvl ? 'selected' : '' ?>>
                                <?= h(ucfirst($lvl)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Correlation ID filter -->
                <div class="col-sm-6 col-md-4">
                    <label for="filter-cid" class="form-label small fw-semibold">Correlation ID</label>
                    <input id="filter-cid"
                           type="text"
                           name="correlation_id"
                           class="form-control form-control-sm font-monospace"
                           placeholder="UUID exact match"
                           value="<?= h($correlationFilter) ?>">
                </div>

                <!-- Lines count -->
                <div class="col-sm-4 col-md-2">
                    <label for="filter-lines" class="form-label small fw-semibold">Lines</label>
                    <input id="filter-lines"
                           type="number"
                           name="lines"
                           class="form-control form-control-sm"
                           min="1"
                           max="1000"
                           value="<?= h((string)$lineCount) ?>">
                </div>

                <!-- Submit -->
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                    <a href="/logs" class="btn btn-outline-secondary btn-sm ms-1">Reset</a>
                </div>

            </form>
        </div>

        <!-- Quick-access line count shortcuts -->
        <div class="card-footer bg-transparent pt-0 border-0">
            <small class="text-muted me-2">Quick:</small>
            <?php foreach ([50, 200, 500] as $quickLines): ?>
                <a href="/logs?lines=<?= $quickLines ?><?= $levelFilter !== '' ? '&level=' . h($levelFilter) : '' ?><?= $correlationFilter !== '' ? '&correlation_id=' . h($correlationFilter) : '' ?>"
                   class="btn btn-outline-secondary btn-sm me-1 <?= $lineCount === $quickLines && $levelFilter === '' && $correlationFilter === '' ? 'active' : '' ?>">
                    Last <?= $quickLines ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ── No log file notice ── -->
    <?php if (!$logFileFound): ?>
        <div class="alert alert-warning" role="alert">
            <strong>No log file found.</strong>
            The file <code>logs/app.log</code> does not exist yet.
            Log entries will appear here once the application has written its first structured log.
        </div>
    <?php elseif (empty($entries)): ?>
        <div class="alert alert-info" role="alert">
            No log entries match the current filters.
        </div>
    <?php else: ?>

    <!-- ── Log entries table ── -->
    <div class="card shadow-sm">
        <div class="card-header d-flex align-items-center gap-2">
            <strong>Log Entries</strong>
            <span class="badge bg-secondary"><?= h((string)count($entries)) ?> shown</span>
            <?php if ($levelFilter !== '' || $correlationFilter !== ''): ?>
                <span class="badge bg-info text-dark">filtered</span>
            <?php endif; ?>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0 font-monospace"
                   style="font-size: 0.8rem;">
                <thead class="table-dark">
                    <tr>
                        <th scope="col" style="white-space: nowrap;">Timestamp</th>
                        <th scope="col">Level</th>
                        <th scope="col" style="min-width: 280px;">Message</th>
                        <th scope="col" style="white-space: nowrap;">Correlation ID</th>
                        <th scope="col" style="min-width: 200px;">Context</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($entries as $entry): ?>
                    <?php
                    $badgeClass = $levelBadge[$entry['level']] ?? 'bg-secondary';
                    // Highlight entire row for error/critical entries to aid at-a-glance triage.
                    $rowClass = in_array($entry['level'], ['error', 'critical'], true)
                        ? 'table-danger'
                        : '';
                    ?>
                    <tr class="<?= $rowClass ?>">
                        <td class="text-muted text-nowrap small">
                            <?= h($entry['timestamp']) ?>
                        </td>
                        <td>
                            <span class="badge <?= h($badgeClass) ?>">
                                <?= h($entry['level']) ?>
                            </span>
                        </td>
                        <td style="word-break: break-word; max-width: 420px;">
                            <?= h($entry['message']) ?>
                        </td>
                        <td class="text-muted small text-nowrap">
                            <?php if ($entry['correlation_id'] !== ''): ?>
                                <?= h($entry['correlation_id']) ?>
                            <?php else: ?>
                                <span class="text-muted fst-italic">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($entry['context'] !== '' && $entry['context'] !== 'null'): ?>
                                <code style="font-size: 0.75rem; word-break: break-all;">
                                    <?= h($entry['context']) ?>
                                </code>
                            <?php else: ?>
                                <span class="text-muted fst-italic">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer text-muted small">
            Showing <?= h((string)count($entries)) ?> of last <?= h((string)$lineCount) ?> lines.
            Max lines: 1000. Refresh the page to reload from file.
        </div>
    </div>

    <?php endif; ?>

</div>
<!-- /.container-fluid -->
