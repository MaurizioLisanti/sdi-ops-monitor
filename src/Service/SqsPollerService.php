<?php
declare(strict_types=1);

namespace App\Service;

use App\Model\Entity\Metric;
use App\Model\Table\MetricsTable;
use Aws\Sqs\SqsClient;
use Cake\Log\Log;

/**
 * SqsPollerService — fetches metric messages from AWS SQS and persists them.
 *
 * Each call to poll() receives up to $maxMessages from the configured queue,
 * validates each message body, persists valid ones via MetricsTable, triggers
 * alert evaluation via AlertsService, and then deletes the message from SQS.
 *
 * Retry policy (explicit):
 *   - Unparsable/invalid messages → deleted immediately; retrying is pointless.
 *   - Valid messages that fail to save → NOT deleted; SQS visibility timeout
 *     causes automatic re-delivery after the configured timeout period.
 *     Infrastructure teams should configure a Dead Letter Queue (DLQ) on the
 *     SQS queue itself to capture messages that exceed maxReceiveCount.
 *
 * Idempotence:
 *   - Before saving, the service checks whether a metric with the same
 *     source + name + recorded_at already exists. Duplicate messages are
 *     deleted from the queue without creating a second DB record.
 *
 * Credentials are never stored in this class — the SqsClient is built by the
 * caller using environment variables only.
 */
class SqsPollerService
{
    /**
     * Required JSON fields in every SQS message body.
     *
     * @var list<string>
     */
    private const REQUIRED_FIELDS = ['source', 'name', 'value', 'recorded_at'];

    /**
     * @param \Aws\Sqs\SqsClient           $sqsClient     Pre-configured SQS client.
     * @param \App\Model\Table\MetricsTable $metricsTable  Persistence layer for Metric entities.
     * @param \App\Service\AlertsService    $alertsService Threshold evaluation service.
     * @param string                        $queueUrl      Full SQS queue URL from env.
     * @param string                        $correlationId UUID v4 for this polling run.
     */
    public function __construct(
        private readonly SqsClient $sqsClient,
        private readonly MetricsTable $metricsTable,
        private readonly AlertsService $alertsService,
        private readonly string $queueUrl,
        private readonly string $correlationId,
    ) {
    }

    /**
     * Poll the SQS queue and process each received message.
     *
     * SQS limits ReceiveMessage to a maximum of 10 messages per call; the
     * $maxMessages parameter is clamped to [1, 10] before the API call.
     *
     * In dry-run mode the service reads and validates messages but does NOT
     * persist them and does NOT delete them from the queue. Messages will
     * become visible again after the SQS visibility timeout.
     *
     * @param int  $maxMessages Maximum messages to fetch per invocation (1–10).
     * @param bool $dryRun      When true, parse only — no DB writes, no deletes.
     * @return list<\App\Model\Entity\Metric> Persisted Metric entities (empty in dry-run).
     */
    public function poll(int $maxMessages = 10, bool $dryRun = false): array
    {
        // SQS hard limit: 1–10 messages per ReceiveMessage call.
        $maxMessages = max(1, min(10, $maxMessages));

        $result = $this->sqsClient->receiveMessage([
            'QueueUrl'            => $this->queueUrl,
            'MaxNumberOfMessages' => $maxMessages,
            // Short poll — the cron interval controls scheduling, not long-poll.
            'WaitTimeSeconds'     => 0,
        ]);

        $messages = $result->get('Messages') ?? [];

        if (empty($messages)) {
            Log::info(json_encode([
                'timestamp'      => date('c'),
                'level'          => 'info',
                'correlation_id' => $this->correlationId,
                'message'        => 'SQS queue is empty — no messages to process.',
                'context'        => ['queue_url' => $this->queueUrl, 'dry_run' => $dryRun],
            ], JSON_THROW_ON_ERROR));

            return [];
        }

        $persisted = [];

        foreach ($messages as $message) {
            $receiptHandle = (string)($message['ReceiptHandle'] ?? '');
            $body          = (string)($message['Body'] ?? '');
            $messageId     = (string)($message['MessageId'] ?? '');

            $data = $this->parseMessageBody($body, $messageId);

            if ($data === null) {
                // Payload is not parsable — delete immediately, no retry is useful.
                if (!$dryRun) {
                    $this->deleteMessage($receiptHandle, $messageId);
                }
                continue;
            }

            if ($dryRun) {
                Log::info(json_encode([
                    'timestamp'      => date('c'),
                    'level'          => 'info',
                    'correlation_id' => $this->correlationId,
                    'message'        => 'Dry-run: message parsed successfully — skipping persist and delete.',
                    'context'        => [
                        'message_id' => $messageId,
                        'source'     => $data['source'],
                        'name'       => $data['name'],
                        'value'      => $data['value'],
                    ],
                ], JSON_THROW_ON_ERROR));
                continue;
            }

            // Idempotence guard: skip if an identical metric already exists.
            if ($this->isAlreadyPersisted($data['source'], $data['name'], $data['recorded_at'])) {
                Log::info(json_encode([
                    'timestamp'      => date('c'),
                    'level'          => 'info',
                    'correlation_id' => $this->correlationId,
                    'message'        => 'Duplicate SQS message detected — metric already persisted, deleting from queue.',
                    'context'        => [
                        'message_id'  => $messageId,
                        'source'      => $data['source'],
                        'name'        => $data['name'],
                        'recorded_at' => $data['recorded_at'],
                    ],
                ], JSON_THROW_ON_ERROR));
                $this->deleteMessage($receiptHandle, $messageId);
                continue;
            }

            $metric = $this->metricsTable->newEntity($data);

            if (!$this->metricsTable->save($metric)) {
                // Do NOT delete — SQS visibility timeout will redeliver for retry.
                Log::error(json_encode([
                    'timestamp'      => date('c'),
                    'level'          => 'error',
                    'correlation_id' => $this->correlationId,
                    'message'        => 'Failed to persist metric from SQS message — will retry via visibility timeout.',
                    'context'        => [
                        'message_id'    => $messageId,
                        'entity_errors' => $metric->getErrors(),
                        'source'        => $data['source'],
                        'name'          => $data['name'],
                    ],
                ], JSON_THROW_ON_ERROR));
                continue;
            }

            // Alert evaluation is best-effort — failures must not block queue processing.
            try {
                $this->alertsService->evaluate($metric, $this->correlationId);
            } catch (\Throwable $e) {
                Log::warning(json_encode([
                    'timestamp'      => date('c'),
                    'level'          => 'warning',
                    'correlation_id' => $this->correlationId,
                    'message'        => 'Alert evaluation failed — metric saved, alert skipped.',
                    'context'        => [
                        'message_id' => $messageId,
                        'metric_id'  => $metric->id,
                        'error'      => $e->getMessage(),
                    ],
                ], JSON_THROW_ON_ERROR));
            }

            // Delete only after successful save — ensures at-least-once processing.
            $this->deleteMessage($receiptHandle, $messageId);

            $persisted[] = $metric;

            Log::info(json_encode([
                'timestamp'      => date('c'),
                'level'          => 'info',
                'correlation_id' => $this->correlationId,
                'message'        => 'Metric persisted from SQS message.',
                'context'        => [
                    'message_id' => $messageId,
                    'metric_id'  => $metric->id,
                    'source'     => $metric->source,
                    'name'       => $metric->name,
                    'value'      => $metric->value,
                ],
            ], JSON_THROW_ON_ERROR));
        }

        return $persisted;
    }

    /**
     * Parse and validate the raw SQS message body.
     *
     * Returns a validated data array on success, or null when the body is
     * not valid JSON or is missing one of the required fields.
     * Invalid messages are logged as warnings and deleted from the queue
     * by the caller (no retry — the payload will always be unparsable).
     *
     * @param string $body      Raw SQS message body string.
     * @param string $messageId SQS message ID for log context.
     * @return array<string, mixed>|null Validated data array, or null on failure.
     */
    public function parseMessageBody(string $body, string $messageId = ''): ?array
    {
        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            Log::warning(json_encode([
                'timestamp'      => date('c'),
                'level'          => 'warning',
                'correlation_id' => $this->correlationId,
                'message'        => 'SQS message body is not valid JSON — deleting from queue.',
                'context'        => ['message_id' => $messageId, 'error' => $e->getMessage()],
            ], JSON_THROW_ON_ERROR));

            return null;
        }

        if (!is_array($data)) {
            Log::warning(json_encode([
                'timestamp'      => date('c'),
                'level'          => 'warning',
                'correlation_id' => $this->correlationId,
                'message'        => 'SQS message body is not a JSON object — deleting from queue.',
                'context'        => ['message_id' => $messageId],
            ], JSON_THROW_ON_ERROR));

            return null;
        }

        foreach (self::REQUIRED_FIELDS as $field) {
            if (!array_key_exists($field, $data)) {
                Log::warning(json_encode([
                    'timestamp'      => date('c'),
                    'level'          => 'warning',
                    'correlation_id' => $this->correlationId,
                    'message'        => 'SQS message missing required field — deleting from queue.',
                    'context'        => ['message_id' => $messageId, 'missing_field' => $field],
                ], JSON_THROW_ON_ERROR));

                return null;
            }
        }

        return $data;
    }

    /**
     * Check whether a metric with the same source, name, and recorded_at already exists.
     *
     * Used to enforce idempotence: SQS delivers at-least-once, so duplicates
     * can arrive. When a match is found, the duplicate is discarded and the
     * message is deleted from the queue without creating a second DB record.
     *
     * @param string $source     Metric origin system identifier.
     * @param string $name       Metric name.
     * @param string $recordedAt ISO 8601 timestamp string.
     * @return bool True when an identical metric already exists in the database.
     */
    public function isAlreadyPersisted(string $source, string $name, string $recordedAt): bool
    {
        return $this->metricsTable->exists([
            'source'      => $source,
            'name'        => $name,
            'recorded_at' => $recordedAt,
        ]);
    }

    /**
     * Delete a processed message from the SQS queue.
     *
     * Called only after the metric has been successfully persisted. If
     * deletion fails, the message will become visible again after the
     * visibility timeout and be re-processed (idempotence handles the duplicate).
     *
     * @param string $receiptHandle SQS receipt handle from the received message.
     * @param string $messageId     SQS message ID for log context.
     * @return void
     */
    public function deleteMessage(string $receiptHandle, string $messageId = ''): void
    {
        try {
            $this->sqsClient->deleteMessage([
                'QueueUrl'      => $this->queueUrl,
                'ReceiptHandle' => $receiptHandle,
            ]);
        } catch (\Throwable $e) {
            // Log and continue — if delete fails, the message becomes visible
            // again and idempotence will handle the re-delivery.
            Log::warning(json_encode([
                'timestamp'      => date('c'),
                'level'          => 'warning',
                'correlation_id' => $this->correlationId,
                'message'        => 'Failed to delete SQS message — idempotence will handle re-delivery.',
                'context'        => ['message_id' => $messageId, 'error' => $e->getMessage()],
            ], JSON_THROW_ON_ERROR));
        }
    }
}
