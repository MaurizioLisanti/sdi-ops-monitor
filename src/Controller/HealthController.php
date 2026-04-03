<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Datasource\ConnectionManager;
use Cake\Http\Response;
use Exception;

/**
 * HealthController — liveness probe endpoint.
 *
 * Responds to GET /health with application and database
 * connectivity status as a JSON payload.
 *
 * Routes: GET /health → 200 {"status":"ok"} | 503 {"status":"error","detail":"..."}
 */
class HealthController extends AppController
{
    /**
     * Perform a liveness check: verify the application is up and the
     * default database connection is reachable via a lightweight SELECT 1 ping.
     *
     * Returns HTTP 200 with {"status":"ok"} when the DB ping succeeds.
     * Returns HTTP 503 with {"status":"error","detail":"<message>"} when it fails.
     * Content-Type is always application/json.
     *
     * @return \Cake\Http\Response JSON liveness response (200 or 503).
     * @throws \Cake\Http\Exception\MethodNotAllowedException When the HTTP method is not GET.
     */
    public function check(): Response
    {
        $this->request->allowMethod('get');

        try {
            $connection = ConnectionManager::get('default');
            // Lightweight ping: executes a trivial query to confirm DB reachability.
            $connection->execute('SELECT 1');

            return $this->response
                ->withStatus(200)
                ->withType('application/json')
                ->withStringBody(json_encode(['status' => 'ok'], JSON_THROW_ON_ERROR));
        } catch (Exception $e) {
            // Any connection or query failure is treated as DB unreachable → 503.
            return $this->response
                ->withStatus(503)
                ->withType('application/json')
                ->withStringBody(json_encode(
                    ['status' => 'error', 'detail' => $e->getMessage()],
                    JSON_THROW_ON_ERROR
                ));
        }
    }
}
