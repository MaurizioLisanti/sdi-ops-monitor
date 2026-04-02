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

    // GET /health — liveness probe (no authentication required in M0).
    // CSRF exemption: GET is a safe HTTP method per RFC 7231; CsrfProtectionMiddleware
    // does not validate tokens on GET/HEAD/OPTIONS requests by default.
    // Route is restricted to GET at routing level so non-safe methods receive
    // HTTP 405 before reaching the controller.
    $routes->scope('/health', function (RouteBuilder $builder): void {
        $builder->connect('/', ['controller' => 'Health', 'action' => 'check'], ['_method' => 'GET']);
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
