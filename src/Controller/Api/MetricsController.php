<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController;
use Cake\Http\Response;

/**
 * MetricsController — REST API for metric ingestion.
 *
 * Handles incoming metric events from external systems (AWS SNS/SQS, HTTP POST).
 * All routes require the `.json` extension or an equivalent Accept header.
 *
 * Routes (prefix: Api, extension: json):
 *   POST   /api/metrics.json        → add()   — ingest a new metric event
 *   GET    /api/metrics.json        → index() — list recent metrics (M1)
 *   GET    /api/metrics/{id}.json   → view()  — single metric detail (M1)
 */
class MetricsController extends AppController
{
    /**
     * Placeholder index action — returns an empty dataset.
     *
     * Full pagination and filtering are deferred to M1.
     *
     * @return \Cake\Http\Response JSON response with an empty data array and zero total.
     */
    public function index(): Response
    {
        // TODO (Planner): paginate MetricsTable query, return JSON.
        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode(['data' => [], 'meta' => ['total' => 0]], JSON_THROW_ON_ERROR));
    }

    /**
     * Ingest a new metric event from the JSON request body.
     *
     * Reads the parsed request body, creates a Metric entity via MetricsTable,
     * validates it, and persists it to the database.
     *
     * On success:  HTTP 201 — {"id": <int>}
     * On failure:  HTTP 422 — {"errors": {<field>: [<message>, ...]}}
     *
     * @return \Cake\Http\Response JSON 201 with new record ID on success,
     *                             JSON 422 with validation error map on failure.
     * @throws \Cake\Http\Exception\MethodNotAllowedException When the HTTP method is not POST.
     */
    public function add(): Response
    {
        $this->request->allowMethod('post');

        $metricsTable = $this->fetchTable('Metrics');
        $metric = $metricsTable->newEntity($this->request->getData());

        if ($metricsTable->save($metric)) {
            return $this->response
                ->withStatus(201)
                ->withType('application/json')
                ->withStringBody(json_encode(['id' => $metric->id], JSON_THROW_ON_ERROR));
        }

        return $this->response
            ->withStatus(422)
            ->withType('application/json')
            ->withStringBody(json_encode(['errors' => $metric->getErrors()], JSON_THROW_ON_ERROR));
    }

    /**
     * Placeholder view action — echoes back the requested record ID only.
     *
     * Full implementation (load from DB, 404 handling) is deferred to M1.
     *
     * @param string $id The metric record identifier from the URL segment.
     * @return \Cake\Http\Response JSON response containing the requested ID.
     * TODO (Planner): load Metric by $id, return 404 if not found.
     */
    public function view(string $id): Response
    {
        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode(['id' => $id], JSON_THROW_ON_ERROR));
    }
}
