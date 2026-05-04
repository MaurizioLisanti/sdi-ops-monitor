<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * AiDiagnosticsControllerTest — integration tests for GET /ai-diagnostics.
 *
 * Covers:
 *   testGetReturns401WithoutAuth  — no Authorization header → 401
 *   testGetReturns200WithAuth     — valid BasicAuth credentials → 200 + HTML page
 *
 * OPENROUTER_API_KEY is intentionally unset so the deterministic fallback is always
 * active — zero real network calls are made in these tests.
 *
 * BasicAuthMiddleware reads credentials from APP_AUTH_USER / APP_AUTH_PASSWORD env
 * vars; setUp() injects test values and tearDown() clears them.
 */
class AiDiagnosticsControllerTest extends TestCase
{
    use IntegrationTestTrait;

    /**
     * Test credentials injected into the environment for BasicAuth validation.
     */
    private const TEST_USER     = 'testuser';
    private const TEST_PASSWORD = 'testpassword';

    /**
     * Inject BasicAuth credentials and clear the OpenRouter API key before each test.
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
        $_SERVER['APP_AUTH_USER']     = self::TEST_USER;
        $_SERVER['APP_AUTH_PASSWORD'] = self::TEST_PASSWORD;

        // No API key — deterministic fallback is active; no network calls in CI.
        putenv('OPENROUTER_API_KEY');
        unset($_ENV['OPENROUTER_API_KEY']);
    }

    /**
     * Remove injected environment variables after each test.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        putenv('APP_AUTH_USER');
        putenv('APP_AUTH_PASSWORD');
        putenv('OPENROUTER_API_KEY');
        unset($_ENV['APP_AUTH_USER'], $_ENV['APP_AUTH_PASSWORD'], $_ENV['OPENROUTER_API_KEY']);

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
     * Verify that GET /ai-diagnostics without credentials returns HTTP 401.
     *
     * BasicAuthMiddleware must enforce authentication before the controller
     * action runs. No Authorization header is sent in this test.
     *
     * @return void
     */
    public function testGetReturns401WithoutAuth(): void
    {
        $this->get('/ai-diagnostics');

        $this->assertResponseCode(401);
    }

    /**
     * Verify that GET /ai-diagnostics with valid BasicAuth credentials returns
     * HTTP 200 and renders the AI Diagnostics page with the fallback badge.
     *
     * OPENROUTER_API_KEY is unset so the deterministic fallback is active;
     * the page must show the "Deterministic Fallback" badge and the page title.
     *
     * @return void
     */
    public function testGetReturns200WithAuth(): void
    {
        $this->configRequest([
            'headers' => ['Authorization' => $this->basicAuthHeader()],
        ]);

        $this->get('/ai-diagnostics');

        $this->assertResponseOk();
        $this->assertContentType('text/html');
        $this->assertResponseContains('AI Diagnostics');
        $this->assertResponseContains('Deterministic Fallback');
    }
}
