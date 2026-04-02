<?php
declare(strict_types=1);

use Cake\Routing\Route\DashedRoute;
use Cake\Routing\RouteBuilder;

/**
 * Routes — sdi-ops-monitor
 *
 * @skeleton M0
 * TODO (Planner): add auth-guarded prefix routes for admin panel if needed.
 */
return static function (RouteBuilder $routes): void {
    $routes->setRouteClass(DashedRoute::class);

    // Health probe — no CSRF
    $routes->scope('/health', function (RouteBuilder $builder): void {
        $builder->connect('/', ['controller' => 'Health', 'action' => 'check']);
    });

    // REST API — /api/metrics
    $routes->prefix('Api', function (RouteBuilder $builder): void {
        $builder->setExtensions(['json']);
        $builder->resources('Metrics');
    });

    // Dashboard (catch-all)
    $routes->scope('/', function (RouteBuilder $builder): void {
        $builder->connect('/', ['controller' => 'Dashboard', 'action' => 'index']);
        $builder->fallbacks(DashedRoute::class);
    });
};
