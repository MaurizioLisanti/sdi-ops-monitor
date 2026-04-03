<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * DashboardControllerTest — integration tests for GET /.
 *
 * Covers:
 *   - Happy path: GET / → HTTP 200 + HTML containing "SDI Ops Monitor"
 *
 * Assumption: MySQL 8.0 is up and migrations have been applied.
 * The metrics and alerts tables may be empty; the controller must handle
 * zero-result queries without errors (A7: overallStatus = 'green').
 */
class DashboardControllerTest extends TestCase
{
    use IntegrationTestTrait;

    /**
     * Verify that GET / returns HTTP 200 and renders a page containing
     * the application name "SDI Ops Monitor".
     *
     * Also confirms that the response content-type is text/html, indicating
     * that the default layout and view template were rendered correctly.
     *
     * @return void
     */
    public function testIndexReturns200(): void
    {
        // GET is a safe method; CSRF middleware does not validate tokens on GET.
        $this->get('/');

        $this->assertResponseOk();
        $this->assertResponseContains('SDI Ops Monitor');
        $this->assertContentType('text/html');
    }
}
