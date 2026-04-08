<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\Http\Middleware\CsrfProtectionMiddleware;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * MetricsControllerTest — integration tests for POST /api/metrics.json.
 *
 * Covers:
 *   - Happy path: valid full payload + valid credentials → HTTP 201 + {"id": <int>}
 *   - Error path: missing required field "name" + valid credentials → HTTP 422 + {"errors": {...}}
 *
 * Assumption: MySQL 8.0 is up and the `metrics` table exists (migrations applied).
 *
 * Since M1 introduced BasicAuthMiddleware, all requests to POST /api/metrics
 * must now supply valid HTTP Basic Auth credentials (A3 updated).
 */
class MetricsControllerTest extends TestCase
{
    use IntegrationTestTrait;

    /**
     * Test credentials — set via environment, never hardcoded in production.
     */
    private const TEST_USER     = 'testuser';
    private const TEST_PASSWORD = 'testpassword';

    /**
     * Inject test credentials into the environment before each test so that
     * BasicAuthMiddleware can validate them.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        putenv('APP_AUTH_USER=' . self::TEST_USER);
        putenv('APP_AUTH_PASSWORD=' . self::TEST_PASSWORD);
        $_ENV['APP_AUTH_USER']     = self::TEST_USER;
        $_ENV['APP_AUTH_PASSWORD'] = self::TEST_PASSWORD;
    }

    /**
     * Remove injected test credentials from the environment after each test.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        putenv('APP_AUTH_USER');
        putenv('APP_AUTH_PASSWORD');
        unset($_ENV['APP_AUTH_USER'], $_ENV['APP_AUTH_PASSWORD']);

        parent::tearDown();
    }

    /**
     * Build the Authorization header value for HTTP Basic Auth.
     *
     * @return string Ready-to-use Authorization header value.
     */
    private function basicAuthHeader(): string
    {
        return 'Basic ' . base64_encode(self::TEST_USER . ':' . self::TEST_PASSWORD);
    }

    /**
     * Configure the integration test request for an AWS SNS HTTP call.
     *
     * When the POST body is a raw JSON string (as SNS sends it), CakePHP's
     * IntegrationTestTrait skips _addTokens() — the CSRF token is never injected
     * automatically. This helper creates a valid CSRF token via the middleware, sets
     * it as both the cookie and the X-CSRF-Token header so that CsrfProtectionMiddleware
     * validation passes, and wires the remaining SNS-specific headers.
     *
     * @param string $messageType Value of X-Amz-Sns-Message-Type (e.g. "Notification").
     * @return void
     */
    private function configureSnsRequest(string $messageType): void
    {
        // Create a cryptographically valid token — _verifyToken() checks the HMAC,
        // so we cannot use an arbitrary string; we must use the middleware factory.
        $csrfMiddleware = new CsrfProtectionMiddleware();
        $csrfToken = $csrfMiddleware->createToken();

        // Set the cookie so CsrfProtectionMiddleware finds it in the request.
        $this->cookie('csrfToken', $csrfToken);

        $this->configRequest([
            'headers' => [
                'Authorization'          => $this->basicAuthHeader(),
                'X-Amz-Sns-Message-Type' => $messageType,
                // JSON content type so BodyParserMiddleware decodes the body;
                // handleSnsRequest() then rewinds the stream to re-read raw JSON.
                'Content-Type'           => 'application/json',
                // Pass the same token in the header — unsaltToken() returns it
                // unchanged (not double-salted length), so hash_equals passes.
                'X-CSRF-Token'           => $csrfToken,
            ],
        ]);
    }

    /**
     * Verify that POST /api/metrics.json with a complete valid payload and
     * valid credentials returns HTTP 201 and a JSON body containing the new
     * record's integer ID.
     *
     * @return void
     */
    public function testAddReturns201(): void
    {
        // CsrfProtectionMiddleware is active globally; enableCsrfToken() injects
        // a valid CSRF cookie and token into the integration test request so that
        // the middleware passes it through without requiring Application.php changes.
        $this->enableCsrfToken();

        // Inject the Authorization header so BasicAuthMiddleware passes the request.
        $this->configRequest([
            'headers' => [
                'Authorization' => $this->basicAuthHeader(),
            ],
        ]);

        $payload = [
            'source'      => 'aws-ec2-prod-01',
            'name'        => 'cpu_usage',
            'value'       => 87.4,
            'unit'        => 'percent',
            'tags'        => ['env' => 'prod', 'region' => 'eu-west-1'],
            'recorded_at' => '2026-04-02 10:00:00',
        ];

        $this->post('/api/metrics.json', $payload);

        $this->assertResponseCode(201);
        $this->assertContentType('application/json');

        $body = json_decode((string)$this->_response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('id', $body);
        $this->assertIsInt($body['id']);
        $this->assertGreaterThan(0, $body['id']);
    }

    /**
     * Verify that POST /api/metrics.json with a payload missing the required
     * field "name" and valid credentials returns HTTP 422 and a JSON body with
     * a non-empty "errors" map.
     *
     * @return void
     */
    public function testAddReturns422OnInvalidPayload(): void
    {
        $this->enableCsrfToken();

        // Inject the Authorization header so BasicAuthMiddleware passes the request.
        $this->configRequest([
            'headers' => [
                'Authorization' => $this->basicAuthHeader(),
            ],
        ]);

        // "name", "value", and "recorded_at" are intentionally omitted to trigger
        // multiple validation errors and confirm they are all surfaced in the response.
        $payload = [
            'source' => 'test-source',
        ];

        $this->post('/api/metrics.json', $payload);

        $this->assertResponseCode(422);
        $this->assertContentType('application/json');

        $body = json_decode((string)$this->_response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('errors', $body);
        $this->assertNotEmpty($body['errors']);
        // Confirm that "name" is among the reported errors.
        $this->assertArrayHasKey('name', $body['errors']);
    }

    /**
     * Verify that a SNS Notification with a non-Amazon SigningCertURL is rejected
     * with HTTP 400 and an "Invalid SNS signature" error body.
     *
     * The domain check in SnsSignatureValidator is purely synchronous: evil.com is
     * rejected against the Amazon allowlist before any outbound network call is made,
     * so this test requires no mocking and makes no real HTTP requests.
     *
     * @return void
     */
    public function testSnsNotificationWithNonAmazonCertUrlReturns400(): void
    {
        $this->configureSnsRequest('Notification');

        $snsBody = json_encode([
            'Type'             => 'Notification',
            'MessageId'        => 'test-msg-001',
            'TopicArn'         => 'arn:aws:sns:us-east-1:123456789012:test-topic',
            'Message'          => '{"source":"test-host","name":"cpu_usage","value":50}',
            'Timestamp'        => '2026-04-08T10:00:00.000Z',
            // evil.com is not in SnsSignatureValidator::ALLOWED_DOMAINS — rejected synchronously.
            'SigningCertURL'   => 'https://evil.com/cert.pem',
            'Signature'        => 'dGVzdA==',
            'SignatureVersion' => '1',
        ], JSON_THROW_ON_ERROR);

        $this->post('/api/metrics.json', $snsBody);

        $this->assertResponseCode(400);
        $this->assertContentType('application/json');

        $body = json_decode((string)$this->_response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('error', $body);
        $this->assertSame('Invalid SNS signature', $body['error']);
    }

    /**
     * Verify that a SNS SubscriptionConfirmation without a SubscribeURL returns
     * HTTP 200 with {"status": "ok"} and no outbound network call is made.
     *
     * SnsSignatureValidator::handleSubscriptionConfirmation() returns early when
     * SubscribeURL is absent, so this path is a safe no-op that needs no mocking.
     *
     * @return void
     */
    public function testSnsSubscriptionConfirmationReturns200(): void
    {
        $this->configureSnsRequest('SubscriptionConfirmation');

        // SubscribeURL is intentionally omitted: handleSubscriptionConfirmation() returns
        // early when the field is empty, so no outbound HTTP request is triggered.
        $snsBody = json_encode([
            'Type'      => 'SubscriptionConfirmation',
            'MessageId' => 'test-sub-001',
            'TopicArn'  => 'arn:aws:sns:us-east-1:123456789012:test-topic',
            'Message'   => 'You have chosen to subscribe to the topic.',
            'Timestamp' => '2026-04-08T10:00:00.000Z',
            'Token'     => 'test-token-abc123',
        ], JSON_THROW_ON_ERROR);

        $this->post('/api/metrics.json', $snsBody);

        $this->assertResponseCode(200);
        $this->assertContentType('application/json');

        $body = json_decode((string)$this->_response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('status', $body);
        $this->assertSame('ok', $body['status']);
    }

    // [DEFERRED: DI container M2+]
    // testSnsNotificationWithInvalidSignatureReturns400 — deferred because injecting a
    // custom SnsSignatureValidator stub (httpGet returning false) into MetricsController at
    // runtime via CakePHP 5 IntegrationTestTrait requires either:
    //   (a) binding MetricsController in Application::services() — forbidden path, or
    //   (b) a custom ControllerFactory registered in Application — forbidden path.
    // The protected factory method createSnsValidator() already provides the extension
    // point; unblock this test in M2+ by wiring DI in Application::services().
}
