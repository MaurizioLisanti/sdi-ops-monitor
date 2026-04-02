<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;

/**
 * HealthController — liveness probe endpoint.
 *
 * Routes: GET /health  →  200 {"status":"ok"}
 *
 * @skeleton M0
 * TODO (Planner): add DB ping check, return 503 if unhealthy.
 */
class HealthController extends AppController
{
    public function check(): Response
    {
        $this->request->allowMethod('get');

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode(['status' => 'ok'], JSON_THROW_ON_ERROR));
    }
}
