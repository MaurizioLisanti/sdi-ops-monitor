<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Model\Table\AlertsTable;
use App\Model\Table\MetricsTable;
use App\Service\AiDiagnosticsService;
use Cake\Http\Client;
use Cake\Http\Client\Response;
use Cake\TestSuite\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

/**
 * AiDiagnosticsServiceTest — unit and integration tests for AiDiagnosticsService::diagnose().
 *
 * Test coverage:
 *   testFallbackActivatesWhenApiKeyAbsent  — no API key → source='fallback'
 *   testFallbackDetectsHighCpuMetric       — metric above threshold → diagnosis mentions CPU
 *   testAiPathWithMockHttpClient           — injected mock Client returns 200 → source='ai'
 *
 * Tests 1 and 2 unset OPENROUTER_API_KEY so the deterministic fallback is always
 * active, guaranteeing zero real network calls. Test 3 injects a PHPUnit mock of
 * Cake\Http\Client::post() that returns a pre-built Cake\Http\Client\Response with
 * a valid OpenRouter JSON payload — also zero real network calls.
 *
 * The MetricsTable and AlertsTable are backed by the test MySQL database.
 * setUp() and tearDown() wipe both tables to ensure test isolation.
 */
class AiDiagnosticsServiceTest extends TestCase
{
    /** @var \App\Model\Table\MetricsTable */
    private MetricsTable $metricsTable;

    /** @var \App\Model\Table\AlertsTable */
    private AlertsTable $alertsTable;

    /**
     * Initialise table references, clean DB state, and clear the API key env var.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        /** @var \App\Model\Table\MetricsTable $metricsTable */
        $metricsTable       = $this->getTableLocator()->get('Metrics');
        $this->metricsTable = $metricsTable;

        /** @var \App\Model\Table\AlertsTable $alertsTable */
        $alertsTable       = $this->getTableLocator()->get('Alerts');
        $this->alertsTable = $alertsTable;

        // Clean state — remove any records left by previous test runs.
        $this->alertsTable->deleteAll([]);
        $this->metricsTable->deleteAll([]);

        // Ensure no API key leaks from the environment into these tests.
        putenv('OPENROUTER_API_KEY');
        unset($_ENV['OPENROUTER_API_KEY']);
    }

    /**
     * Remove inserted rows and unset the API key after each test.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->alertsTable->deleteAll([]);
        $this->metricsTable->deleteAll([]);

        putenv('OPENROUTER_API_KEY');
        unset($_ENV['OPENROUTER_API_KEY']);

        parent::tearDown();
    }

    /**
     * Verify that the deterministic fallback is activated when OPENROUTER_API_KEY
     * is absent from the environment.
     *
     * The API key is cleared in setUp(); no HTTP client mock is injected.
     * The service must return source='fallback' without attempting any network call.
     *
     * @return void
     */
    public function testFallbackActivatesWhenApiKeyAbsent(): void
    {
        $service = new AiDiagnosticsService($this->metricsTable, $this->alertsTable);
        $result  = $service->diagnose('test-corr-fallback-1');

        $this->assertSame('fallback', $result->source, 'Source must be fallback when API key is absent.');
        $this->assertSame('deterministic-fallback', $result->model);
        $this->assertNotEmpty($result->diagnosis, 'Fallback must always produce a non-empty diagnosis.');
        $this->assertNotEmpty($result->generatedAt);
        $this->assertSame('test-corr-fallback-1', $result->correlationId);
        $this->assertSame(0, $result->metricsCount, 'Empty DB → 0 metrics counted.');
        $this->assertSame(0, $result->alertsCount,  'Empty DB → 0 alerts counted.');
    }

    /**
     * Verify that the fallback detects a metric value above the cpu_usage threshold (80.0)
     * and includes a reference to it in the diagnosis text.
     *
     * A single Metric with cpu_usage=92.5 is inserted before the call. The service
     * must report a threshold violation for CPU and reflect metricsCount=1.
     *
     * @return void
     */
    public function testFallbackDetectsHighCpuMetric(): void
    {
        // Insert a metric that exceeds the 80.0 cpu_usage threshold.
        $metric = $this->metricsTable->newEntity([
            'source'      => 'aws-ec2-prod-01',
            'name'        => 'cpu_usage',
            'value'       => 92.5,
            'unit'        => 'percent',
            'recorded_at' => date('Y-m-d H:i:s'),
        ]);
        $this->metricsTable->saveOrFail($metric);

        $service = new AiDiagnosticsService($this->metricsTable, $this->alertsTable);
        $result  = $service->diagnose('test-corr-fallback-2');

        $this->assertSame('fallback', $result->source);
        $this->assertSame(1, $result->metricsCount, 'One metric was inserted.');
        // Diagnosis must reference the CPU threshold violation.
        $this->assertStringContainsStringIgnoringCase(
            'cpu',
            $result->diagnosis,
            'Fallback diagnosis must mention the CPU threshold violation.',
        );
    }

    /**
     * Verify that the AI path is taken when OPENROUTER_API_KEY is set and
     * the injected HTTP client returns a valid OpenRouter JSON response.
     *
     * A PHPUnit mock of Cake\Http\Client replaces the real client so no network
     * call is made. The mock returns a pre-built Cake\Http\Client\Response with
     * status 200 and a JSON body matching the OpenRouter response schema.
     *
     * @return void
     */
    public function testAiPathWithMockHttpClient(): void
    {
        // Set a dummy API key so the service enters the AI branch.
        putenv('OPENROUTER_API_KEY=test-mock-key');
        $_ENV['OPENROUTER_API_KEY'] = 'test-mock-key';

        // Build a real Cake\Http\Client\Response — isOk() returns true for HTTP 200.
        $responseJson = (string)json_encode([
            'choices' => [
                ['message' => ['content' => 'All systems are operating within normal parameters.']]
            ],
        ]);
        $mockResponse = new Response(
            ['HTTP/1.1 200 OK', 'Content-Type: application/json'],
            $responseJson,
        );

        // Mock only the post() method — prevent any real HTTP call.
        $mockClient = $this->getMockBuilder(Client::class)
            ->onlyMethods(['post'])
            ->getMock();
        $mockClient
            ->expects($this->once())
            ->method('post')
            ->willReturn($mockResponse);

        $service = new AiDiagnosticsService(
            $this->metricsTable,
            $this->alertsTable,
            $mockClient,
        );
        $result = $service->diagnose('test-corr-ai-3');

        $this->assertSame('ai', $result->source, 'Source must be ai when the mock client returns 200.');
        $this->assertStringContainsString(
            'normal parameters',
            $result->diagnosis,
            'Diagnosis must contain the text from the mock response.',
        );
        $this->assertSame('test-corr-ai-3', $result->correlationId);
    }

    /**
     * Verify that the deterministic fallback activates when the HTTP client
     * throws — i.e. the exact failure modes the fallback exists for
     * (timeout, DNS failure, connection refused).
     *
     * With a valid API key present, the service enters the AI branch and calls
     * Client::post(). When that call throws any Throwable, the service MUST
     * catch it and degrade to source='fallback' rather than letting the
     * exception propagate and 500 the request.
     *
     * Regression guard: this test fails (errors) when the `use Throwable;`
     * import is missing from AiDiagnosticsService, because the catch clause
     * then resolves to the non-existent App\Service\Throwable and never matches.
     *
     * @return void
     */
    public function testFallbackActivatesWhenHttpClientThrows(): void
    {
        // API key present → the service enters the AI branch and calls post().
        putenv('OPENROUTER_API_KEY=test-mock-key');
        $_ENV['OPENROUTER_API_KEY'] = 'test-mock-key';

        // Simulate a network failure: post() throws a Throwable.
        // A plain RuntimeException is sufficient — the production catch is the
        // generic `catch (Throwable $e)`, so any Throwable must be handled.
        $mockClient = $this->getMockBuilder(Client::class)
            ->onlyMethods(['post'])
            ->getMock();
        $mockClient
            ->expects($this->once())
            ->method('post')
            ->willThrowException(new RuntimeException('Simulated network failure'));

        $service = new AiDiagnosticsService(
            $this->metricsTable,
            $this->alertsTable,
            $mockClient,
        );
        $result = $service->diagnose('test-corr-throw-4');

        $this->assertSame(
            'fallback',
            $result->source,
            'A Throwable from the HTTP client must degrade to the deterministic fallback, not propagate.',
        );
        $this->assertSame('deterministic-fallback', $result->model);
        $this->assertNotEmpty($result->diagnosis, 'Fallback must always produce a non-empty diagnosis.');
        $this->assertSame('test-corr-throw-4', $result->correlationId);
    }

    /**
     * Insert a batch of metrics representing one moment in the flow.
     *
     * @param array<string, float> $values Metric name => value.
     * @param string $source Reporting source name.
     * @return void
     */
    private function seedMetrics(array $values, string $source = 'sdi-batch-milano-01'): void
    {
        foreach ($values as $name => $value) {
            $metric = $this->metricsTable->newEntity([
                'source' => $source,
                'name' => $name,
                'value' => $value,
                'unit' => 'unit',
                'recorded_at' => date('Y-m-d H:i:s'),
            ]);
            $this->metricsTable->saveOrFail($metric);
        }
    }

    /**
     * A lag with no rejections must be diagnosed as a stalled channel.
     *
     * This is the case the old per-metric fallback could not express: it would
     * report "invoices_pending 8266" and leave the operator to guess. Naming the
     * stall matters because it rules out the wrong remedy — re-transmitting
     * files that were already accepted.
     *
     * @return void
     */
    public function testFallbackIdentifiesStalledChannel(): void
    {
        $this->seedMetrics([
            'sdi_receipt_lag_minutes' => 95.0,
            'sdi_rejection_rate' => 1.1,
            'invoices_pending' => 8266.0,
            'signing_cert_expiry_days' => 180.0,
            'cpu_usage' => 31.0,
        ]);

        $result = (new AiDiagnosticsService($this->metricsTable, $this->alertsTable))
            ->diagnose('test-corr-stalled');

        $this->assertSame('fallback', $result->source);
        $this->assertStringContainsStringIgnoringCase(
            'stalled',
            $result->diagnosis,
            'A lag without rejections must be named as a stalled queue.'
        );
        $this->assertStringContainsStringIgnoringCase(
            'not a data problem',
            $result->diagnosis,
            'The diagnosis must rule out a data cause, since files are not being refused.'
        );
    }

    /**
     * Saturated infrastructure alongside a lag must be offered as the cause.
     *
     * Same service-level symptom as the test above, opposite explanation. The
     * two-tier metric model exists precisely so these can be told apart.
     *
     * @return void
     */
    public function testFallbackAttributesLagToSaturationWhenPresent(): void
    {
        $this->seedMetrics([
            'sdi_receipt_lag_minutes' => 95.0,
            'sdi_rejection_rate' => 0.9,
            'cpu_usage' => 97.0,
            'memory_usage' => 94.0,
        ]);

        $result = (new AiDiagnosticsService($this->metricsTable, $this->alertsTable))
            ->diagnose('test-corr-saturated');

        $this->assertStringContainsStringIgnoringCase(
            'saturated',
            $result->diagnosis,
            'Concurrent saturation must be offered as the likely cause of the lag.'
        );
    }

    /**
     * A total rejection rate with an expiring certificate must name code 00100.
     *
     * The operator needs the code, not a description: it is what appears in the
     * Notifica di Scarto and what makes the cause unambiguous.
     *
     * @return void
     */
    public function testFallbackNamesCertificateRejectionCode(): void
    {
        $this->seedMetrics([
            'sdi_rejection_rate' => 98.0,
            'signing_cert_expiry_days' => 0.0,
            'sdi_receipt_lag_minutes' => 6.0,
        ]);

        $result = (new AiDiagnosticsService($this->metricsTable, $this->alertsTable))
            ->diagnose('test-corr-cert-expired');

        $this->assertStringContainsString(
            '00100',
            $result->diagnosis,
            'An expired certificate with total rejection must name code 00100.'
        );
        $this->assertStringContainsString(
            '00002',
            $result->diagnosis,
            'Re-transmission advice must warn about the duplicate file name code.'
        );
    }

    /**
     * Total rejections with a healthy certificate must point at the payload.
     *
     * Ruling the certificate out is the point: it sends the operator to the
     * schema and identification controls instead of to a renewal they do not
     * need.
     *
     * @return void
     */
    public function testFallbackPointsAtPayloadWhenCertificateIsValid(): void
    {
        $this->seedMetrics([
            'sdi_rejection_rate' => 87.0,
            'signing_cert_expiry_days' => 240.0,
            'sdi_receipt_lag_minutes' => 5.0,
        ]);

        $result = (new AiDiagnosticsService($this->metricsTable, $this->alertsTable))
            ->diagnose('test-corr-payload');

        $this->assertStringContainsString(
            '00200',
            $result->diagnosis,
            'A payload rejection must name the schema conformance code.'
        );
        $this->assertStringNotContainsString(
            '00100',
            $result->diagnosis,
            'A valid certificate must not be blamed.'
        );
    }

    /**
     * An expiring certificate on a healthy flow must still produce a warning.
     *
     * Nothing has broken, so every threshold-based check stays silent — yet this
     * is the only situation in the system where an outage can still be avoided.
     *
     * @return void
     */
    public function testFallbackWarnsBeforeCertificateExpiryWithHealthyFlow(): void
    {
        $this->seedMetrics([
            'sdi_receipt_lag_minutes' => 3.0,
            'sdi_rejection_rate' => 0.8,
            'invoices_pending' => 55.0,
            'signing_cert_expiry_days' => 5.0,
            'cpu_usage' => 28.0,
        ]);

        $result = (new AiDiagnosticsService($this->metricsTable, $this->alertsTable))
            ->diagnose('test-corr-cert-warning');

        $this->assertStringContainsStringIgnoringCase(
            'expires in 5',
            $result->diagnosis,
            'The remaining validity must be stated explicitly.'
        );
        $this->assertStringContainsStringIgnoringCase(
            'renewal',
            $result->diagnosis,
            'The recommended action must be to start renewal.'
        );
    }

    /**
     * A healthy certificate must never be reported as a problem.
     *
     * Regression guard for the direction of the comparison: read upwards, 240
     * exceeds every threshold in the rule-set and a perfectly valid certificate
     * becomes an alert.
     *
     * @return void
     */
    public function testHealthyCertificateIsNotReportedAsBreach(): void
    {
        $this->seedMetrics([
            'sdi_receipt_lag_minutes' => 2.0,
            'signing_cert_expiry_days' => 240.0,
        ]);

        $result = (new AiDiagnosticsService($this->metricsTable, $this->alertsTable))
            ->diagnose('test-corr-cert-healthy');

        $this->assertStringNotContainsStringIgnoringCase(
            'certificate',
            $result->diagnosis,
            'A certificate valid for 240 days must not appear in the diagnosis at all.'
        );
    }

    /**
     * The diagnosis must never fall back to telling the operator to ask someone else.
     *
     * The screenshot that motivated this work showed the old fallback answering
     * "contact technical support and check the error logs" — fluent, on-topic
     * and completely useless. This test pins that regression across every
     * failure mode, because the value of the feature is precisely that it does
     * not do this.
     *
     * @return array<string, array{0: array<string, float>}>
     */
    public static function failureModeProvider(): array
    {
        return [
            'stalled channel' => [[
                'sdi_receipt_lag_minutes' => 95.0,
                'sdi_rejection_rate' => 1.0,
            ]],
            'certificate expired' => [[
                'sdi_rejection_rate' => 99.0,
                'signing_cert_expiry_days' => 0.0,
            ]],
            'payload refused' => [[
                'sdi_rejection_rate' => 80.0,
                'signing_cert_expiry_days' => 200.0,
            ]],
            'certificate expiring' => [[
                'sdi_receipt_lag_minutes' => 2.0,
                'signing_cert_expiry_days' => 4.0,
            ]],
            'pending only' => [[
                'invoices_pending' => 900.0,
            ]],
        ];
    }

    /**
     * @param array<string, float> $values Metrics describing the failure mode.
     * @return void
     */
    #[DataProvider('failureModeProvider')]
    public function testDiagnosisNeverDefersToSupport(array $values): void
    {
        $this->seedMetrics($values);

        $result = (new AiDiagnosticsService($this->metricsTable, $this->alertsTable))
            ->diagnose('test-corr-no-deferral');

        foreach (['contact support', 'contact technical support', 'check the error logs'] as $phrase) {
            $this->assertStringNotContainsStringIgnoringCase(
                $phrase,
                $result->diagnosis,
                sprintf('The diagnosis must not defer to "%s".', $phrase)
            );
        }
        $this->assertNotEmpty($result->diagnosis);
    }
}
