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
    // One explicit route rather than resources(). Two reasons.
    //
    // resources() maps index, view, edit and delete alongside create; those
    // actions do not exist, so the routes resolved and then failed inside the
    // framework. Restricting it with ['only' => ['create']] stops the mapping
    // but leaves the URL to be swallowed by the catch-all further down, whose
    // behaviour varies between environments — locally a 404, on CI a 500.
    //
    // Binding the method here instead makes the answer deterministic and more
    // accurate than either: GET /api/metrics returns 405 Method Not Allowed,
    // which tells a caller the endpoint exists and takes POST, rather than
    // implying the path is wrong.
    $routes->prefix('Api', function (RouteBuilder $builder): void {
        $builder->setExtensions(['json']);
        $builder->connect(
            '/metrics',
            ['controller' => 'Metrics', 'action' => 'add'],
            ['_method' => 'POST'],
        );
    });

    // Dashboard (catch-all)
    $routes->scope('/', function (RouteBuilder $builder): void {
        $builder->connect('/', ['controller' => 'Dashboard', 'action' => 'index']);
        $builder->fallbacks(DashedRoute::class);
    });
};
