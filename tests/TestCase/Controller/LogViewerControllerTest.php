<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * LogViewerControllerTest — integration tests for GET /logs.
 *
 * Covers:
 *   - testIndexReturns200WithValidCredentials   — valid Basic Auth → 200 + HTML
 *   - testIndexReturns401WithoutCredentials     — no auth header   → 401
 *   - testIndexHandlesMissingLogFile            — log file absent  → 200 + "No log file"
 *
 * The log file path is hardcoded in LogViewerController (ROOT/logs/app.log).
 * Tests that exercise the missing-file path rename the real file temporarily,
 * and restore it in tearDown to avoid side-effects across the suite.
 *
 * BasicAuthMiddleware is active in the middleware stack; test credentials are
 * injected via environment variables in setUp() and cleared in tearDown().
 */
class LogViewerControllerTest extends TestCase
{
    use IntegrationTestTrait;

    /**
     * Username used for all authenticated requests.
     */
    private const TEST_USER = 'testuser';

    /**
     * Password used for all authenticated requests.
     */
    private const TEST_PASSWORD = 'testpassword';

    /**
     * Absolute path to the log file as expected by LogViewerController.
     */
    private string $logFilePath;

    /**
     * Temporary backup path used when the real log file is hidden from the controller.
     */
    private string $logFileBackupPath;

    /**
     * Whether the log file was renamed (hidden) during this test method.
     * Used by tearDown() to decide whether to restore it.
     */
    private bool $logFileHidden = false;

    /**
     * Inject test credentials into the environment and resolve the log file paths.
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

        // Mirror the path constructed by LogViewerController.
        $this->logFilePath       = ROOT . DS . 'logs' . DS . 'app.log';
        $this->logFileBackupPath = ROOT . DS . 'logs' . DS . 'app.log.test_bak';
    }

    /**
     * Remove injected credentials and restore any hidden log file.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        putenv('APP_AUTH_USER');
        putenv('APP_AUTH_PASSWORD');
        unset($_ENV['APP_AUTH_USER'], $_ENV['APP_AUTH_PASSWORD']);
        unset($_SERVER['APP_AUTH_USER'], $_SERVER['APP_AUTH_PASSWORD']);

        // Restore the real log file if it was hidden during the test.
        if ($this->logFileHidden && file_exists($this->logFileBackupPath)) {
            rename($this->logFileBackupPath, $this->logFilePath);
            $this->logFileHidden = false;
        }

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
     * Temporarily hide the real log file so the controller sees no file.
     *
     * Renames app.log to app.log.test_bak. The original is restored in tearDown().
     * If no log file exists this method is a no-op.
     *
     * @return void
     */
    private function hideLogFile(): void
    {
        if (file_exists($this->logFilePath)) {
            rename($this->logFilePath, $this->logFileBackupPath);
            $this->logFileHidden = true;
        }
    }

    /**
     * Verify that GET /logs with valid HTTP Basic Auth credentials returns HTTP 200
     * and renders an HTML page containing the "Log Viewer" heading.
     *
     * @return void
     */
    public function testIndexReturns200WithValidCredentials(): void
    {
        $this->configRequest([
            'headers' => [
                'Authorization' => $this->basicAuthHeader(),
            ],
        ]);

        $this->get('/logs');

        $this->assertResponseOk();
        $this->assertResponseContains('Log Viewer');
        $this->assertContentType('text/html');
    }

    /**
     * Verify that GET /logs without an Authorization header returns HTTP 401.
     *
     * BasicAuthMiddleware must intercept the request before it reaches the
     * controller and return a 401 with the WWW-Authenticate challenge header.
     *
     * @return void
     */
    public function testIndexReturns401WithoutCredentials(): void
    {
        $this->get('/logs');

        $this->assertResponseCode(401);
    }

    /**
     * Verify that GET /logs returns HTTP 200 with a "No log file" notice when
     * logs/app.log does not exist.
     *
     * The controller must never raise a 500 for a missing log file — it should
     * degrade gracefully by setting logFileFound=false and letting the template
     * display the informational message.
     *
     * @return void
     */
    public function testIndexHandlesMissingLogFile(): void
    {
        // Hide the real log file (if any) so the controller cannot find it.
        $this->hideLogFile();

        $this->configRequest([
            'headers' => [
                'Authorization' => $this->basicAuthHeader(),
            ],
        ]);

        $this->get('/logs');

        $this->assertResponseOk();
        $this->assertResponseContains('No log file found');
    }
}
