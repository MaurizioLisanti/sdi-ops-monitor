<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\Database\Connection;
use Cake\Database\Driver\Mysql;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * HealthControllerTest — integration tests for GET /health.
 *
 * Covers the happy path (DB reachable → HTTP 200) and the error path
 * (DB unreachable → HTTP 503) of the liveness probe endpoint.
 */
class HealthControllerTest extends TestCase
{
    use IntegrationTestTrait;

    /**
     * Verify that GET /health returns HTTP 200 with body {"status":"ok"}
     * and Content-Type application/json when the database is reachable.
     *
     * @return void
     */
    public function testHealthReturns200(): void
    {
        $this->get('/health');

        $this->assertResponseOk();
        $this->assertResponseContains('"status":"ok"');
        $this->assertContentType('application/json');
    }

    /**
     * Verify that GET /health returns HTTP 503 with body containing "status":"error"
     * and Content-Type application/json when the database connection is not reachable.
     *
     * Simulation strategy:
     * - In the test environment, ConnectionHelper::addTestAliases() maps
     *   'default' → 'test' via $_aliasMap so that all test code uses the
     *   test database. To bypass this alias and inject a bad connection, we:
     *   1. Drop the alias with ConnectionManager::dropAlias('default').
     *   2. Register a new 'default' config with invalid credentials.
     *   3. Execute the request — HealthController::check() calls get('default')
     *      and receives the bad connection → execute('SELECT 1') throws → 503.
     * - The finally block always restores the alias so subsequent tests are
     *   not affected.
     *
     * @return void
     */
    public function testHealthReturns503WhenDbDown(): void
    {
        // Remove the test alias so 'default' is no longer redirected to 'test'.
        ConnectionManager::dropAlias('default');
        // Drop the real 'default' config (set from app_local.php during bootstrap)
        // so we can replace it with the bad-credentials config below.
        ConnectionManager::drop('default');

        // Register a 'default' config that will fail to connect.
        ConnectionManager::setConfig('default', [
            'className' => Connection::class,
            'driver' => Mysql::class,
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'sdi_nonexistent_db',
            'username' => 'invalid_test_user',
            'password' => 'invalid_test_pass',
            'log' => false,
            'quoteIdentifiers' => false,
        ]);

        try {
            $this->get('/health');
        } finally {
            // Remove the bad config and restore the test alias to prevent
            // test pollution for subsequent test methods.
            ConnectionManager::drop('default');
            ConnectionManager::alias('test', 'default');
        }

        $this->assertResponseCode(503);
        $this->assertResponseContains('"status":"error"');
        $this->assertContentType('application/json');
    }
}
