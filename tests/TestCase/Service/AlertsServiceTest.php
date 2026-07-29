<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Model\Entity\Alert;
use App\Model\Entity\Metric;
use App\Model\Table\AlertsTable;
use App\Service\AlertsService;
use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

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
 *   4. Countdown thresholds (direction='below') fire when the value drops.
 *   5. Countdown thresholds stay silent while the value is comfortably high.
 *   6. Direction is per-rule, so a rule-set may mix both directions.
 *   7. An unknown direction falls back to 'above' rather than silencing the rule.
 *   8. Alert wording follows the direction that was breached.
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
        // Remove any threshold injected by a test so rule-sets never leak
        // across cases — Configure is global state.
        Configure::delete('Thresholds.signing_cert_expiry_days');
        Configure::delete('Thresholds.mixed_direction_metric');
        Configure::delete('Thresholds.typo_direction_metric');

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

    /**
     * A countdown threshold must fire when the measured value falls to or below it.
     *
     * signing_cert_expiry_days is the motivating case: the SDI rejects every
     * transmission with code 00100 once the signing certificate has expired, so
     * the operator needs warning while the number is still shrinking. A rule
     * expressed as "value >= threshold" cannot say this at all.
     *
     * @return void
     */
    public function testCountdownThresholdFiresWhenValueDrops(): void
    {
        Configure::write('Thresholds.signing_cert_expiry_days', [
            ['threshold' => 30.0, 'severity' => 'high', 'direction' => 'below'],
            ['threshold' => 7.0, 'severity' => 'critical', 'direction' => 'below'],
        ]);

        // 5 days left: breaches both rules, critical must win.
        $metric = new Metric(['name' => 'signing_cert_expiry_days', 'value' => 5.0]);

        $result = $this->service->evaluate($metric, '3f2b8c14-9d7e-4a51-bc63-0e8f5a2d9147');

        $this->assertInstanceOf(
            Alert::class,
            $result,
            'A countdown metric below its threshold must produce an Alert.'
        );
        $this->assertSame(
            'critical',
            $result->severity,
            '5 days remaining must trigger critical (threshold <= 7), not high.'
        );
    }

    /**
     * A countdown threshold must stay silent while the value is still healthy.
     *
     * This is the test that would fail if direction were ignored: with the
     * default "above" comparison, 90 >= 30 and 90 >= 7 both hold, so a
     * perfectly valid certificate would raise a critical alert.
     *
     * @return void
     */
    public function testCountdownThresholdSilentWhenValueIsHigh(): void
    {
        Configure::write('Thresholds.signing_cert_expiry_days', [
            ['threshold' => 30.0, 'severity' => 'high', 'direction' => 'below'],
            ['threshold' => 7.0, 'severity' => 'critical', 'direction' => 'below'],
        ]);

        $countBefore = $this->alertsTable->find()->count();

        // 90 days left: healthy, nothing to report.
        $metric = new Metric(['name' => 'signing_cert_expiry_days', 'value' => 90.0]);

        $result = $this->service->evaluate($metric, '3f2b8c14-9d7e-4a51-bc63-0e8f5a2d9147');

        $this->assertNull(
            $result,
            'A certificate valid for 90 more days must not raise an alert.'
        );
        $this->assertSame(
            $countBefore,
            $this->alertsTable->find()->count(),
            'No Alert must be persisted for a healthy countdown metric.'
        );
    }

    /**
     * Direction is a property of the individual rule, not of the whole rule-set.
     *
     * A metric may legitimately be unhealthy at both ends. Here only the
     * "below" rule is breached, so evaluation must consider each rule on its
     * own terms rather than applying one comparison to all of them.
     *
     * @return void
     */
    public function testDirectionIsResolvedPerRule(): void
    {
        Configure::write('Thresholds.mixed_direction_metric', [
            ['threshold' => 100.0, 'severity' => 'high'],
            ['threshold' => 10.0, 'severity' => 'critical', 'direction' => 'below'],
        ]);

        $metric = new Metric(['name' => 'mixed_direction_metric', 'value' => 4.0]);

        $result = $this->service->evaluate($metric, '3f2b8c14-9d7e-4a51-bc63-0e8f5a2d9147');

        $this->assertInstanceOf(
            Alert::class,
            $result,
            'The "below" rule must fire even though the "above" rule does not.'
        );
        $this->assertSame(
            'critical',
            $result->severity,
            'Only the countdown rule is breached, so its severity must win.'
        );
    }

    /**
     * An unrecognised direction must fall back to "above", not disable the rule.
     *
     * A typo in app_local.php must never turn a threshold into a no-op: a
     * monitor that silently stops watching is more dangerous than one that
     * fires conservatively.
     *
     * @return void
     */
    public function testUnknownDirectionFallsBackToAbove(): void
    {
        Configure::write('Thresholds.typo_direction_metric', [
            ['threshold' => 50.0, 'severity' => 'high', 'direction' => 'belwo'],
        ]);

        $metric = new Metric(['name' => 'typo_direction_metric', 'value' => 80.0]);

        $result = $this->service->evaluate($metric, '3f2b8c14-9d7e-4a51-bc63-0e8f5a2d9147');

        $this->assertInstanceOf(
            Alert::class,
            $result,
            'A misspelled direction must not silence the threshold.'
        );
    }

    /**
     * The alert message must describe the breach in the direction it happened.
     *
     * "Value 5 exceeded critical threshold" is simply false for a countdown
     * metric, and an operator reading it at 3am would look for the wrong cause.
     *
     * @return void
     */
    public function testAlertWordingFollowsBreachDirection(): void
    {
        Configure::write('Thresholds.signing_cert_expiry_days', [
            ['threshold' => 7.0, 'severity' => 'critical', 'direction' => 'below'],
        ]);

        $metric = new Metric(['name' => 'signing_cert_expiry_days', 'value' => 5.0]);
        $result = $this->service->evaluate($metric, '3f2b8c14-9d7e-4a51-bc63-0e8f5a2d9147');

        $this->assertInstanceOf(Alert::class, $result);
        $this->assertStringContainsString(
            'dropped below',
            (string)$result->message,
            'A countdown breach must be worded as a drop, not as an excess.'
        );
        $this->assertStringNotContainsString(
            'exceeded',
            (string)$result->message,
            'A countdown breach must never be described as "exceeded".'
        );
    }

    /**
     * The built-in defaults must cover the four service metrics.
     *
     * These are the numbers a deployment inherits when it configures nothing.
     * If a metric silently has no rule-set, it is not being watched at all —
     * so this test asserts the contract of the defaults rather than trusting
     * that the constant was written correctly.
     *
     * @return array<string, array{0: string, 1: float, 2: string}>
     */
    public static function serviceMetricDefaultsProvider(): array
    {
        return [
            // Receipts stalled for two hours: the channel has stopped answering.
            'receipt lag 120 min is critical' => ['sdi_receipt_lag_minutes', 120.0, 'critical'],
            'receipt lag 45 min is high' => ['sdi_receipt_lag_minutes', 45.0, 'high'],
            // A rejection rate moving as a step function means one systematic cause.
            'rejection rate 20% is critical' => ['sdi_rejection_rate', 20.0, 'critical'],
            'rejection rate 7% is high' => ['sdi_rejection_rate', 7.0, 'high'],
            // Volume-dependent, but never unmonitored.
            'pending 2500 is critical' => ['invoices_pending', 2500.0, 'critical'],
            'pending 800 is high' => ['invoices_pending', 800.0, 'high'],
            // Countdown: an expired certificate stops every transmission at once.
            'cert 3 days left is critical' => ['signing_cert_expiry_days', 3.0, 'critical'],
            'cert 20 days left is high' => ['signing_cert_expiry_days', 20.0, 'high'],
        ];
    }

    /**
     * Each service metric must reach the expected severity using only defaults.
     *
     * @param string $metricName The metric under test.
     * @param float $value The measured value.
     * @param string $expectedSeverity The severity the defaults should produce.
     * @return void
     */
    #[DataProvider('serviceMetricDefaultsProvider')]
    public function testServiceMetricDefaults(
        string $metricName,
        float $value,
        string $expectedSeverity
    ): void {
        $metric = new Metric(['name' => $metricName, 'value' => $value]);

        $result = $this->service->evaluate($metric, '3f2b8c14-9d7e-4a51-bc63-0e8f5a2d9147');

        $this->assertInstanceOf(
            Alert::class,
            $result,
            sprintf('%s = %.4g must raise an alert on built-in defaults.', $metricName, $value)
        );
        $this->assertSame(
            $expectedSeverity,
            $result->severity,
            sprintf('%s = %.4g must resolve to %s.', $metricName, $value, $expectedSeverity)
        );
    }

    /**
     * Healthy service metrics must stay silent on the built-in defaults.
     *
     * The certificate case is the one that matters: a certificate valid for
     * another 200 days must not alert, even though 200 is a large number and
     * the naive comparison would treat it as a breach.
     *
     * @return array<string, array{0: string, 1: float}>
     */
    public static function healthyServiceMetricProvider(): array
    {
        return [
            'receipt lag 4 min' => ['sdi_receipt_lag_minutes', 4.0],
            'rejection rate 1.2%' => ['sdi_rejection_rate', 1.2],
            'pending 60 invoices' => ['invoices_pending', 60.0],
            'cert valid 200 more days' => ['signing_cert_expiry_days', 200.0],
        ];
    }

    /**
     * @param string $metricName The metric under test.
     * @param float $value A value that should be considered healthy.
     * @return void
     */
    #[DataProvider('healthyServiceMetricProvider')]
    public function testHealthyServiceMetricsDoNotAlert(string $metricName, float $value): void
    {
        $countBefore = $this->alertsTable->find()->count();

        $metric = new Metric(['name' => $metricName, 'value' => $value]);
        $result = $this->service->evaluate($metric, '3f2b8c14-9d7e-4a51-bc63-0e8f5a2d9147');

        $this->assertNull(
            $result,
            sprintf('%s = %.4g is healthy and must not alert.', $metricName, $value)
        );
        $this->assertSame(
            $countBefore,
            $this->alertsTable->find()->count(),
            'No Alert row must be written for a healthy metric.'
        );
    }

    /**
     * Infrastructure metrics must survive the move to domain thresholds.
     *
     * cpu_usage and memory_usage were the original thresholds and are kept as
     * diagnostic context — the explanation for why receipts are lagging. This
     * test exists so a future cleanup cannot quietly drop them.
     *
     * @return void
     */
    public function testInfrastructureMetricsStillEvaluated(): void
    {
        foreach (['cpu_usage' => 97.0, 'memory_usage' => 96.0] as $name => $value) {
            $metric = new Metric(['name' => $name, 'value' => $value]);

            $result = $this->service->evaluate($metric, '3f2b8c14-9d7e-4a51-bc63-0e8f5a2d9147');

            $this->assertInstanceOf(
                Alert::class,
                $result,
                sprintf('%s must still be evaluated as diagnostic context.', $name)
            );
            $this->assertSame('critical', $result->severity);
        }
    }
}
