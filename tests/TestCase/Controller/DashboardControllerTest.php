<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * DashboardControllerTest — smoke test.
 *
 * @skeleton M0
 * TODO (Planner): add fixture loading, assert view vars, test alert count display.
 */
class DashboardControllerTest extends TestCase
{
    use IntegrationTestTrait;

    public function testIndexReturns200(): void
    {
        $this->get('/');
        $this->assertResponseOk();
        $this->assertResponseContains('SDI Ops Monitor');
    }
}
