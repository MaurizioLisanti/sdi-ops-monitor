<?php
declare(strict_types=1);

/**
 * webroot/index.php — CakePHP 5 front controller.
 *
 * @skeleton M0
 */

use App\Application;
use Cake\Http\Server;

require dirname(__DIR__) . '/vendor/autoload.php';

$app    = new Application(dirname(__DIR__) . '/config');
$server = new Server($app);

$server->emit($server->run());
