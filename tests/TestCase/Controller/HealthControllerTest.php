<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * HealthControllerTest — probe smoke test.
 *
 * @skeleton M0
 * TODO (Planner): mock DB connection, assert 503 when DB is down.
 */
class HealthControllerTest extends TestCase
{
    use IntegrationTestTrait;

    public function testHealthReturns200(): void
    {
        $this->get('/health');
        $this->assertResponseOk();
        $this->assertResponseContains('"status":"ok"');
    }
}
