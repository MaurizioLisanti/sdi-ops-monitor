<?php
/**
 * Default layout — sdi-ops-monitor
 *
 * @skeleton M0
 * TODO (Planner): integrate chosen CSS framework (Tailwind / Bootstrap).
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($this->fetch('title')) ?> — SDI Ops Monitor</title>
    <?= $this->Html->css([]) ?>
</head>
<body>
    <header>
        <nav>
            <a href="/">SDI Ops Monitor</a>
            <a href="/health">Health</a>
        </nav>
    </header>

    <main>
        <?= $this->Flash->render() ?>
        <?= $this->fetch('content') ?>
    </main>

    <footer>
        <small>SDI Ops Monitor</small>
    </footer>
    <?= $this->Html->script([]) ?>
</body>
</html>
