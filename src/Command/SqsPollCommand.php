<?php
declare(strict_types=1);

namespace App\Command;

use App\Model\Table\AlertsTable;
use App\Service\AlertsService;
use App\Service\SqsPollerService;
use Aws\Sqs\SqsClient;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\ORM\TableRegistry;
use Cake\Utility\Text;

/**
 * SqsPollCommand — CakePHP 5 CLI command for polling an AWS SQS queue.
 *
 * Designed to be triggered by an external scheduler (cron, systemd timer,
 * AWS EventBridge Scheduler) at a fixed interval (e.g. every 60 seconds).
 * Each invocation fetches up to --max-messages messages, validates them,
 * persists valid ones via MetricsTable, and evaluates alert thresholds.
 *
 * Usage:
 *   bin/cake sqs_poll
 *   bin/cake sqs_poll --max-messages=5
 *   bin/cake sqs_poll --dry-run
 *
 * Environment variables required (never hardcode):
 *   AWS_SQS_QUEUE_URL      — full SQS queue URL
 *   AWS_REGION             — AWS region, e.g. eu-west-1
 *   AWS_ACCESS_KEY_ID      — optional when using IAM instance/task role
 *   AWS_SECRET_ACCESS_KEY  — optional when using IAM instance/task role
 *   AWS_SESSION_TOKEN      — optional for temporary IAM role credentials
 */
class SqsPollCommand extends Command
{
    /**
     * Injected service instance used in tests to bypass real AWS calls.
     * When null, buildPollerService() constructs the real service from env vars.
     *
     * @var \App\Service\SqsPollerService|null
     */
    private ?SqsPollerService $pollerService = null;

    /**
     * Register the CLI command name used by bin/cake.
     *
     * @return string
     */
    public static function defaultName(): string
    {
        return 'sqs_poll';
    }

    /**
     * Define CLI options for this command.
     *
     * @param \Cake\Console\ConsoleOptionParser $parser The option parser to configure.
     * @return \Cake\Console\ConsoleOptionParser
     */
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->setDescription('Poll an AWS SQS queue and persist incoming metric messages.')
            ->addOption('max-messages', [
                'help'    => 'Maximum number of messages to receive per run (1–10, SQS limit).',
                'default' => 10,
                'short'   => 'm',
            ])
            ->addOption('dry-run', [
                'help'    => 'Parse and validate messages without persisting or deleting them.',
                'boolean' => true,
                'default' => false,
                'short'   => 'd',
            ]);

        return $parser;
    }

    /**
     * Inject a pre-built SqsPollerService, bypassing the real AWS client.
     *
     * Intended for use in tests: call this before execute() to replace the
     * service that would otherwise be constructed from environment variables.
     *
     * @param \App\Service\SqsPollerService $service The service instance to use.
     * @return void
     */
    public function setPollerService(SqsPollerService $service): void
    {
        $this->pollerService = $service;
    }

    /**
     * Execute the SQS polling command.
     *
     * Generates a correlation ID for this run, builds or reuses the poller
     * service, and delegates processing to SqsPollerService::poll().
     * Exits CODE_SUCCESS even when the queue is empty — an empty queue is
     * not an error condition. Exits CODE_ERROR only on fatal configuration
     * problems (e.g. missing queue URL).
     *
     * @param \Cake\Console\Arguments  $args Parsed CLI arguments and options.
     * @param \Cake\Console\ConsoleIo  $io   Console input/output interface.
     * @return int Command::CODE_SUCCESS (0) or Command::CODE_ERROR (1).
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $correlationId = Text::uuid();
        $maxMessages   = (int)$args->getOption('max-messages');
        $dryRun        = (bool)$args->getOption('dry-run');

        $io->out(json_encode([
            'timestamp'      => date('c'),
            'level'          => 'info',
            'correlation_id' => $correlationId,
            'message'        => 'SQS poll started.',
            'context'        => [
                'max_messages' => $maxMessages,
                'dry_run'      => $dryRun,
            ],
        ], JSON_THROW_ON_ERROR));

        $service = $this->pollerService;

        if ($service === null) {
            $queueUrl = (string)env('AWS_SQS_QUEUE_URL', '');

            if ($queueUrl === '') {
                $io->error(json_encode([
                    'timestamp'      => date('c'),
                    'level'          => 'error',
                    'correlation_id' => $correlationId,
                    'message'        => 'AWS_SQS_QUEUE_URL is not set — cannot poll.',
                    'context'        => [],
                ], JSON_THROW_ON_ERROR));

                return self::CODE_ERROR;
            }

            $service = $this->buildPollerService($queueUrl, $correlationId);
        }

        $processed = $service->poll($maxMessages, $dryRun);

        $io->out(json_encode([
            'timestamp'      => date('c'),
            'level'          => 'info',
            'correlation_id' => $correlationId,
            'message'        => 'SQS poll completed.',
            'context'        => [
                'messages_processed' => count($processed),
                'dry_run'            => $dryRun,
            ],
        ], JSON_THROW_ON_ERROR));

        return self::CODE_SUCCESS;
    }

    /**
     * Build a SqsPollerService from environment variables.
     *
     * Credentials are read exclusively from environment variables — never
     * hardcoded. When AWS_ACCESS_KEY_ID and AWS_SECRET_ACCESS_KEY are absent,
     * the SDK falls back to the EC2/ECS instance role credential chain
     * (instance metadata service), which is the preferred production mode.
     *
     * @param string $queueUrl      Full SQS queue URL from AWS_SQS_QUEUE_URL.
     * @param string $correlationId UUID v4 for this polling run.
     * @return \App\Service\SqsPollerService
     */
    protected function buildPollerService(string $queueUrl, string $correlationId): SqsPollerService
    {
        $region = (string)env('AWS_REGION', 'eu-west-1');

        $clientConfig = [
            'version' => 'latest',
            'region'  => $region,
        ];

        // Only set explicit credentials when provided; otherwise the SDK uses
        // the EC2/ECS instance role via the credential provider chain.
        $accessKey = env('AWS_ACCESS_KEY_ID');
        $secretKey = env('AWS_SECRET_ACCESS_KEY');

        if ($accessKey && $secretKey) {
            $clientConfig['credentials'] = [
                'key'    => (string)$accessKey,
                'secret' => (string)$secretKey,
            ];

            // Temporary session credentials for assumed IAM roles.
            $sessionToken = env('AWS_SESSION_TOKEN');
            if ($sessionToken) {
                $clientConfig['credentials']['token'] = (string)$sessionToken;
            }
        }

        /** @var \App\Model\Table\MetricsTable $metricsTable */
        $metricsTable = TableRegistry::getTableLocator()->get('Metrics');

        /** @var \App\Model\Table\AlertsTable $alertsTable */
        $alertsTable = TableRegistry::getTableLocator()->get('Alerts');

        return new SqsPollerService(
            new SqsClient($clientConfig),
            $metricsTable,
            new AlertsService($alertsTable),
            $queueUrl,
            $correlationId,
        );
    }
}
