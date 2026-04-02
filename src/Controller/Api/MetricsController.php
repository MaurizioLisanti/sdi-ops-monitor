<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController;
use Cake\Http\Response;

/**
 * MetricsController — REST API for metric ingestion.
 *
 * Routes:
 *   POST   /api/metrics        → ingest a new metric event
 *   GET    /api/metrics        → list recent metrics (paginated)
 *   GET    /api/metrics/{id}   → single metric detail
 *
 * @skeleton M0
 * TODO (Planner): validate payload, persist via MetricsTable, publish to SNS.
 */
class MetricsController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->loadModel('Metrics');
    }

    public function index(): Response
    {
        // TODO (Planner): paginate MetricsTable query, return JSON.
        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode(['data' => [], 'meta' => ['total' => 0]], JSON_THROW_ON_ERROR));
    }

    public function add(): Response
    {
        $this->request->allowMethod('post');
        // TODO (Planner): parse body, create Metric entity, save, return 201.
        return $this->response
            ->withStatus(201)
            ->withType('application/json')
            ->withStringBody(json_encode(['status' => 'accepted'], JSON_THROW_ON_ERROR));
    }

    public function view(string $id): Response
    {
        // TODO (Planner): load Metric by $id, return 404 if not found.
        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode(['id' => $id], JSON_THROW_ON_ERROR));
    }
}
