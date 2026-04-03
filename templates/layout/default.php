<?php
/**
 * Default layout — sdi-ops-monitor
 *
 * Integrates Bootstrap 5 via CDN for responsive UI and traffic-light components.
 * Bootstrap 5.3 is loaded from jsDelivr CDN; requires internet access in development.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($this->fetch('title')) ?> — SDI Ops Monitor</title>
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet"
          crossorigin="anonymous">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-0">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="/">SDI Ops Monitor</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="/">Dashboard</a>
                <a class="nav-link" href="/health">Health</a>
            </div>
        </div>
    </nav>

    <main>
        <?= $this->Flash->render() ?>
        <?= $this->fetch('content') ?>
    </main>

    <footer class="bg-light text-muted py-2 mt-4 border-top">
        <div class="container-fluid">
            <small>SDI Ops Monitor</small>
        </div>
    </footer>

    <!-- Bootstrap 5.3 JS bundle (includes Popper.js) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
            crossorigin="anonymous"></script>
</body>
</html>
