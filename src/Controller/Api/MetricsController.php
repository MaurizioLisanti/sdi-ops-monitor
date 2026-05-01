<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController;
use App\Service\AlertsService;
use App\Service\SnsSignatureValidator;
use Cake\Http\Response;
use Cake\Log\Log;
use JsonException;
use Throwable;

/**
 * MetricsController — REST API for metric ingestion.
 *
 * Handles incoming metric events from external systems (AWS SNS/SQS, HTTP POST).
 * All routes require the `.json` extension or an equivalent Accept header.
 *
 * Routes (prefix: Api, extension: json):
 * POST /api/metrics.json → add() — ingest a new metric event
 * GET /api/metrics.json → index() — list recent metrics (M1)
 * GET /api/metrics/{id}.json → view() — single metric detail (M1)
 *
 * SNS pipeline (M1):
 * When the X-Amz-Sns-Message-Type header is present, add() routes the request
 * through the SNS-specific pipeline before any metric is written:
 * SubscriptionConfirmation → confirm subscription → 200 (no metric saved)
 * Notification + valid sig → extract metric from Message field → normal save
 * Notification + invalid sig or bad cert URL → 400 (no metric saved)
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
     * When the X-Amz-Sns-Message-Type header is present, the request is
     * processed through the SNS pipeline (signature validation + message
     * type routing) before any metric data is written to the database.
     *
     * When the header is absent the original metric ingestion behaviour is
     * unchanged: validate the payload and persist a Metric entity.
     *
     * On success (direct): HTTP 201 — {"id": <int>}
     * On success (SNS subscription): HTTP 200 — {"status": "ok"}
     * On validation failure: HTTP 422 — {"errors": {<field>: [<message>]}}
     * On SNS signature failure: HTTP 400 — {"error": "<reason>"}
     *
     * @return \Cake\Http\Response
     * @throws \Cake\Http\Exception\MethodNotAllowedException When the HTTP method is not POST.
     */
    public function add(): Response
    {
        $this->request->allowMethod('post');

        $snsMessageType = $this->request->getHeaderLine('X-Amz-Sns-Message-Type');

        // Route to SNS pipeline when the AWS header is present.
        if ($snsMessageType !== '') {
            return $this->handleSnsRequest($snsMessageType);
        }

        // Normal metric ingestion pipeline (unchanged from M0).
        return $this->ingestMetric($this->request->getData());
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

    /**
     * Handle an incoming request identified as an AWS SNS message.
     *
     * Two SNS message types are supported:
     * - SubscriptionConfirmation: confirm the subscription and return 200.
     * - Notification: validate the signature; on success extract and ingest
     * the metric embedded in the Message field; on failure return 400.
     *
     * The raw body is read by rewinding the PSR-7 stream, which is necessary
     * because BodyParserMiddleware has already consumed it once.
     *
     * @param string $messageType Value of the X-Amz-Sns-Message-Type header.
     * @return \Cake\Http\Response
     */
    private function handleSnsRequest(string $messageType): Response
    {
        $correlationId = (string)($this->request->getAttribute('correlation_id') ?? '');

        // Rewind the stream before re-reading — BodyParserMiddleware consumed it earlier.
        $this->request->getBody()->rewind();
        $rawBody = (string)$this->request->getBody();

        try {
            $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            return $this->jsonResponse(400, ['error' => 'Invalid JSON body']);
        }

        if (!is_array($payload)) {
            return $this->jsonResponse(400, ['error' => 'Invalid SNS payload']);
        }

        $validator = $this->createSnsValidator();

        if ($messageType === 'SubscriptionConfirmation') {
            Log::info(json_encode([
                'timestamp' => date('c'),
                'level' => 'info',
                'correlation_id' => $correlationId,
                'message' => 'SNS SubscriptionConfirmation received.',
                'context' => ['topic_arn' => $payload['TopicArn'] ?? null],
            ], JSON_THROW_ON_ERROR));

            $validator->handleSubscriptionConfirmation($payload);

            return $this->jsonResponse(200, ['status' => 'ok']);
        }

        if ($messageType === 'Notification') {
            $headers = $this->request->getHeaders();

            if (!$validator->validate($headers, $rawBody)) {
                Log::warning(json_encode([
                    'timestamp' => date('c'),
                    'level' => 'warning',
                    'correlation_id' => $correlationId,
                    'message' => 'SNS Notification rejected: signature validation failed.',
                    'context' => ['topic_arn' => $payload['TopicArn'] ?? null],
                ], JSON_THROW_ON_ERROR));

                return $this->jsonResponse(400, ['error' => 'Invalid SNS signature']);
            }

            // SNS wraps the metric JSON as a serialised string inside the Message field.
            $messageData = [];
            if (isset($payload['Message'])) {
                $decoded = json_decode((string)$payload['Message'], true);
                if (is_array($decoded)) {
                    $messageData = $decoded;
                }
            }

            Log::info(json_encode([
                'timestamp' => date('c'),
                'level' => 'info',
                'correlation_id' => $correlationId,
                'message' => 'SNS Notification accepted — ingesting metric.',
                'context' => ['topic_arn' => $payload['TopicArn'] ?? null],
            ], JSON_THROW_ON_ERROR));

            return $this->ingestMetric($messageData, $correlationId);
        }

        // Unrecognised SNS message type — acknowledge and ignore (fail-open for
        // future SNS message types that do not carry metric data).
        Log::info(json_encode([
            'timestamp' => date('c'),
            'level' => 'info',
            'correlation_id' => $correlationId,
            'message' => 'SNS message type not handled — acknowledged and ignored.',
            'context' => ['message_type' => $messageType],
        ], JSON_THROW_ON_ERROR));

        return $this->jsonResponse(200, ['status' => 'ok']);
    }

    /**
     * Create and return an SnsSignatureValidator instance.
     *
     * Extracted from handleSnsRequest() to allow test subclasses to override
     * this method and inject a stub validator, enabling controller-level HTTP
     * tests without real network calls. Production code always returns the
     * default validator (uses file_get_contents with TLS verification).
     *
     * @return \App\Service\SnsSignatureValidator
     */
    protected function createSnsValidator(): SnsSignatureValidator
    {
        return new SnsSignatureValidator();
    }

    /**
     * Validate and persist a metric entity from a raw data array.
     *
     * Shared between the normal HTTP pipeline and the SNS Notification pipeline.
     * On save success, evaluates alert thresholds via AlertsService.
     *
     * @param array<string, mixed> $data Associative array of metric field values.
     * @param string $correlationId Correlation ID for log entries (optional).
     * @return \Cake\Http\Response HTTP 201 with {"id": <int>} on success,
     * HTTP 422 with {"errors": {...}} on validation failure.
     */
    private function ingestMetric(array $data, string $correlationId = ''): Response
    {
        if ($correlationId === '') {
            $correlationId = (string)($this->request->getAttribute('correlation_id') ?? '');
        }

        $metricsTable = $this->fetchTable('Metrics');
        $metric = $metricsTable->newEntity($data);

        if ($metricsTable->save($metric)) {
            // Evaluate alert thresholds on a best-effort basis.
            // Any exception thrown by the service is caught and logged so that
            // the 201 response is always returned even when alert creation fails.
            try {
                $alertsService = new AlertsService($this->fetchTable('Alerts'));
                $alertsService->evaluate($metric, $correlationId);
            } catch (Throwable $e) {
                Log::error(json_encode([
                    'timestamp' => date('c'),
                    'level' => 'error',
                    'correlation_id' => $correlationId,
                    'message' => 'AlertsService::evaluate() failed — metric saved, alert skipped.',
                    'context' => [
                        'exception' => $e->getMessage(),
                        'metric_id' => $metric->id,
                    ],
                ], JSON_THROW_ON_ERROR));
            }

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
     * Build a JSON response with the given status code and body data.
     *
     * @param int $status HTTP status code.
     * @param array<string, mixed> $body Data to JSON-encode as the response body.
     * @return \Cake\Http\Response
     */
    private function jsonResponse(int $status, array $body): Response
    {
        return $this->response
            ->withStatus($status)
            ->withType('application/json')
            ->withStringBody(json_encode($body, JSON_THROW_ON_ERROR));
    }
}
