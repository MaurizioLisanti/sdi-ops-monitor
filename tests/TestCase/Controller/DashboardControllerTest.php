<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * DashboardControllerTest — integration tests for GET /.
 *
 * Covers:
 *   - Happy path: GET / with valid credentials → HTTP 200 + HTML containing "SDI Ops Monitor"
 *
 * Assumption: MySQL 8.0 is up and migrations have been applied.
 * The metrics and alerts tables may be empty; the controller must handle
 * zero-result queries without errors (A7: overallStatus = 'green').
 *
 * Since M1 introduced BasicAuthMiddleware, all requests to GET / must now
 * supply valid HTTP Basic Auth credentials (A3 updated).
 */
class DashboardControllerTest extends TestCase
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
     * Verify that GET / with valid HTTP Basic Auth credentials returns HTTP 200
     * and renders a page containing the application name "SDI Ops Monitor".
     *
     * Also confirms that the response content-type is text/html, indicating
     * that the default layout and view template were rendered correctly.
     *
     * @return void
     */
    public function testIndexReturns200(): void
    {
        // Inject the Authorization header so BasicAuthMiddleware passes the request.
        $this->configRequest([
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode(self::TEST_USER . ':' . self::TEST_PASSWORD),
            ],
        ]);

        // GET is a safe method; CSRF middleware does not validate tokens on GET.
        $this->get('/');

        $this->assertResponseOk();
        $this->assertResponseContains('SDI Ops Monitor');
        $this->assertContentType('text/html');
    }
}
