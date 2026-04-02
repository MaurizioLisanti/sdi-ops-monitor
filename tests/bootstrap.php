<?php
declare(strict_types=1);

/**
 * tests/bootstrap.php — PHPUnit bootstrap for sdi-ops-monitor.
 *
 * @skeleton M0
 */

use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;

require dirname(__DIR__) . '/vendor/autoload.php';

define('ROOT', dirname(__DIR__));
define('APP', ROOT . '/src/');
define('TESTS', ROOT . '/tests/');
define('TMP', ROOT . '/tmp/');
define('LOGS', ROOT . '/logs/');

Configure::write('App.namespace', 'App');
Configure::write('debug', true);

ConnectionManager::setConfig('test', [
    'className' => 'Cake\Database\Connection',
    'driver'    => 'Cake\Database\Driver\Mysql',
    'host'      => env('DB_HOST', '127.0.0.1'),
    'port'      => env('DB_PORT', '3306'),
    'username'  => env('DB_USERNAME', 'sdi_user'),
    'password'  => env('DB_PASSWORD', 'secret'),
    'database'  => env('DB_NAME', 'sdi_ops_monitor_test'),
    'encoding'  => 'utf8mb4',
    'timezone'  => 'UTC',
]);
