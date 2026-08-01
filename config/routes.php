<?php
declare(strict_types=1);

use Cake\Routing\Route\DashedRoute;
use Cake\Routing\RouteBuilder;

/**
 * Routes — sdi-ops-monitor
 *
 * @skeleton M0
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
    //
    // Restricted to the create action deliberately. resources() would also map
    // index, view, edit and delete; those actions do not exist, so the routes
    // would resolve and then fail inside the framework — a 500 where a 404 is
    // the honest answer. Listing the action explicitly keeps the routing table
    // and the controller in agreement: what is routable is what is implemented.
    $routes->prefix('Api', function (RouteBuilder $builder): void {
        $builder->setExtensions(['json']);
        $builder->resources('Metrics', ['only' => ['create']]);
    });

    // Dashboard (catch-all)
    $routes->scope('/', function (RouteBuilder $builder): void {
        $builder->connect('/', ['controller' => 'Dashboard', 'action' => 'index']);
        $builder->fallbacks(DashedRoute::class);
    });
};
