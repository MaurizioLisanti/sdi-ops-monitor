<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Model\Entity\Alert;
use App\Model\Entity\Metric;
use App\Model\Table\AlertsTable;
use App\Service\AlertsService;
use Cake\TestSuite\TestCase;

/**
 * AlertsServiceTest — unit/integration tests for AlertsService::evaluate().
 *
 * Uses the test database (aliased by CakePHP's test runner) to verify that
 * Alert entities are correctly created and persisted when metric thresholds
 * are breached.
 *
 * Test coverage:
 *   1. Value above the highest threshold → Alert created with 'critical' severity.
 *   2. Value below all thresholds         → null returned, no Alert persisted.
 *   3. Unknown metric name                → null returned, no Alert persisted.
 */
class AlertsServiceTest extends TestCase
{
    private AlertsService $service;

    /** @var \App\Model\Table\AlertsTable */
    private AlertsTable $alertsTable;

    /**
     * Initialise a clean AlertsService backed by the test database.
     * Removes any leftover alerts from a previous run to guarantee isolation.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        /** @var \App\Model\Table\AlertsTable $alertsTable */
        $alertsTable       = $this->getTableLocator()->get('Alerts');
        $this->alertsTable = $alertsTable;

        // Clean state: remove all alerts so counts are deterministic.
        $this->alertsTable->deleteAll([]);

        $this->service = new AlertsService($this->alertsTable);
    }

    /**
     * Clear the table locator after each test to prevent cross-test contamination.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->getTableLocator()->clear();
        parent::tearDown();
    }

    /**
     * When a metric value exceeds the critical threshold (cpu_usage ≥ 95),
     * evaluate() must return an Alert entity with severity='critical' and
     * status='open', and the entity must be persisted in the database.
     *
     * @return void
     */
    public function testEvaluateCreatesAlertWhenThresholdExceeded(): void
    {
        // cpu_usage = 96 → breaches both high (≥80) and critical (≥95) rules.
        // The service must pick the highest severity: 'critical'.
        $metric = new Metric(['name' => 'cpu_usage', 'value' => 96.0]);

        $result = $this->service->evaluate($metric, '07fb3dcf-3145-4619-aa93-9616794580f5');

        $this->assertInstanceOf(
            Alert::class,
            $result,
            'evaluate() must return an Alert entity when a threshold is exceeded.'
        );
        $this->assertNotEmpty(
            $result->id,
            'The returned Alert must have been persisted (non-empty ID).'
        );
        $this->assertSame(
            'critical',
            $result->severity,
            'cpu_usage=96 must trigger critical severity (threshold ≥ 95).'
        );
        $this->assertSame(
            'open',
            $result->status,
            'Newly created alerts must have status "open".'
        );

        // Verify the alert actually exists in the database.
        $savedAlert = $this->alertsTable->get($result->id);
        $this->assertSame('critical', $savedAlert->severity);
    }

    /**
     * When a metric value is below all configured thresholds (cpu_usage=60 < 80),
     * evaluate() must return null without creating any Alert.
     *
     * @return void
     */
    public function testEvaluateSkipsAlertBelowThreshold(): void
    {
        $countBefore = $this->alertsTable->find()->count();

        $metric = new Metric(['name' => 'cpu_usage', 'value' => 60.0]);
        $result = $this->service->evaluate($metric, '07fb3dcf-3145-4619-aa93-9616794580f5');

        $this->assertNull(
            $result,
            'evaluate() must return null when the metric value is below all thresholds.'
        );
        $this->assertSame(
            $countBefore,
            $this->alertsTable->find()->count(),
            'No Alert must be persisted when no threshold is breached.'
        );
    }

    /**
     * When the metric name has no configured thresholds (neither in Configure
     * nor in the built-in defaults), evaluate() must return null immediately.
     *
     * @return void
     */
    public function testEvaluateReturnsNullForUnknownMetric(): void
    {
        $metric = new Metric(['name' => 'unknown_metric_xyz', 'value' => 999.0]);
        $result = $this->service->evaluate($metric, '07fb3dcf-3145-4619-aa93-9616794580f5');

        $this->assertNull(
            $result,
            'evaluate() must return null for metric names with no threshold configuration.'
        );
    }
}
