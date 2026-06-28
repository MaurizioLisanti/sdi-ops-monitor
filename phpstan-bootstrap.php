<?php
declare(strict_types=1);

/**
 * Minimal bootstrap for static analysis (PHPStan).
 *
 * Loads CakePHP path constants (ROOT, CAKE, ...) and global helper functions
 * (env(), ...) WITHOUT booting the app or connecting to the database.
 * PHPStan analyses code, it does not execute it — no DB needed.
 */
require __DIR__ . '/config/paths.php';
require CAKE . 'functions.php';
