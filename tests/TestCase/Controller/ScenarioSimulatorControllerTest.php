<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * ScenarioSimulatorControllerTest — integration tests for GET /simulate and POST /simulate/run.
 *
 * Covers:
 *   - testIndexReturns200             — GET /simulate with BasicAuth → 200 + HTML form
 *   - testRunReturns200WithValidScenario — POST /simulate/run with scenario-3 → 200 + results
 *   - testRunRejectsMissingScenarioId — POST /simulate/run without scenario_id → 422
 *   - testRunDryRunDoesNotPersist     — POST with dry_run=1 → 200, no Metric inserted in DB
 *
 * scenario-3 ("Normal Operation — All Clear") is used for the non-dry-run happy path
 * because it produces zero alerts, keeping the DB clean during the test run.
 *
 * BasicAuthMiddleware is active in the middleware stack; credentials are injected
 * via environment variables in setUp() and cleared in tearDown().
 * CsrfProtectionMiddleware is satisfied by enableCsrfToken() in each POST test.
 */
class ScenarioSimulatorControllerTest extends TestCase
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
     * Inject BasicAuth credentials into the environment before each test.
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
     * Remove injected credentials from the environment after each test.
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
     * Verify that GET /simulate with valid BasicAuth credentials returns HTTP 200
     * and renders the scenario selection form.
     *
     * The page must contain the heading and at least one scenario name to confirm
     * that the catalogue was passed to the view correctly.
     *
     * @return void
     */
    public function testIndexReturns200(): void
    {
        $this->configRequest([
            'headers' => [
                'Authorization' => $this->basicAuthHeader(),
            ],
        ]);

        $this->get('/simulate');

        $this->assertResponseOk();
        $this->assertContentType('text/html');
        $this->assertResponseContains('Scenario Simulator');
        // Verify at least one scenario card is rendered.
        $this->assertResponseContains('CPU Spike');
    }

    /**
     * Verify that POST /simulate/run with a valid scenario_id returns HTTP 200
     * and renders the results page showing the scenario name and correlation ID.
     *
     * scenario-3 ("Normal Operation — All Clear") is chosen because it produces
     * zero alerts, limiting DB side-effects to three harmless Metric inserts.
     *
     * @return void
     */
    public function testRunReturns200WithValidScenario(): void
    {
        // enableCsrfToken() injects a valid CSRF cookie + _csrfToken field into
        // the POST data automatically so CsrfProtectionMiddleware passes through.
        $this->enableCsrfToken();

        $this->configRequest([
            'headers' => [
                'Authorization' => $this->basicAuthHeader(),
            ],
        ]);

        $this->post('/simulate/run', [
            'scenario_id' => 'scenario-3',
        ]);

        $this->assertResponseOk();
        $this->assertContentType('text/html');
        // Results page must show the scenario name and the correlation ID section.
        $this->assertResponseContains('Normal Operation');
        $this->assertResponseContains('Correlation ID');
    }

    /**
     * Verify that POST /simulate/run without a scenario_id returns HTTP 422
     * and re-renders the selection form with a validation error message.
     *
     * The controller must reject any POST body where scenario_id is absent or
     * not present in the scenario catalogue. HTTP 422 signals Unprocessable Entity.
     *
     * @return void
     */
    public function testRunRejectsMissingScenarioId(): void
    {
        $this->enableCsrfToken();

        $this->configRequest([
            'headers' => [
                'Authorization' => $this->basicAuthHeader(),
            ],
        ]);

        // Post without a scenario_id — the controller must return 422.
        $this->post('/simulate/run', []);

        $this->assertResponseCode(422);
        $this->assertContentType('text/html');
        $this->assertResponseContains('Invalid or missing scenario_id');
    }

    /**
     * Verify that POST /simulate/run with dry_run=1 returns HTTP 200 and does
     * not insert any Metric records in the database.
     *
     * The DB row count is snapshotted before and after the request; they must
     * be identical to confirm that ScenarioService skipped all MetricsTable::save()
     * calls when $dryRun === true.
     *
     * @return void
     */
    public function testRunDryRunDoesNotPersist(): void
    {
        $this->enableCsrfToken();

        $this->configRequest([
            'headers' => [
                'Authorization' => $this->basicAuthHeader(),
            ],
        ]);

        // Count existing metrics before the request.
        $metricsTable = $this->getTableLocator()->get('Metrics');
        $countBefore  = $metricsTable->find()->count();

        $this->post('/simulate/run', [
            'scenario_id' => 'scenario-1',
            'dry_run'     => '1',
        ]);

        $this->assertResponseOk();
        $this->assertContentType('text/html');
        $this->assertResponseContains('DRY RUN');

        // Metric count must be unchanged — no records written to the database.
        $countAfter = $metricsTable->find()->count();
        $this->assertSame($countBefore, $countAfter, 'Dry run must not insert any Metric records.');
    }
}
