<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\AiDiagnosticsService;
use App\Service\ScenarioService;
use Cake\ORM\Table;
use Cake\TestSuite\TestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * ScenarioServiceTest — verifies that the scenario catalogue tells the truth.
 *
 * The catalogue is documentation as much as it is code: each entry claims how
 * many alerts it raises and which failure mode it demonstrates, and an operator
 * is invited to use those claims to check the pipeline is wired correctly. That
 * only works while the claims match behaviour, and prose has no way of noticing
 * when it stops matching — the invented SDI error codes this repository used to
 * carry were exactly that kind of drift, sitting in the README for weeks.
 *
 * These tests run every scenario against the database and assert the outcome,
 * so a change to a threshold or to a scenario's metric values breaks the build
 * rather than quietly making the catalogue wrong.
 *
 * Test coverage:
 *   1. Catalogue entries are structurally complete.
 *   2. Each scenario raises exactly the number of alerts it claims.
 *   3. Each scenario's metrics produce the failure mode it describes.
 *   4. Dry-run persists nothing.
 *   5. An unknown scenario id is rejected.
 */
class ScenarioServiceTest extends TestCase
{
    private ScenarioService $service;

    private Table $metricsTable;

    private Table $alertsTable;

    /**
     * Start each test from empty tables so alert counts are attributable to one run.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->metricsTable = $this->getTableLocator()->get('Metrics');
        $this->alertsTable = $this->getTableLocator()->get('Alerts');

        $this->alertsTable->deleteAll([]);
        $this->metricsTable->deleteAll([]);

        $this->service = new ScenarioService($this->metricsTable, $this->alertsTable);
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $this->alertsTable->deleteAll([]);
        $this->metricsTable->deleteAll([]);

        $this->getTableLocator()->clear();
        parent::tearDown();
    }

    /**
     * Every scenario id in the catalogue, for use as a data provider.
     *
     * Reads the catalogue rather than hard-coding ids, so a new scenario is
     * covered by these tests the moment it is added — an untested scenario is
     * how a catalogue starts drifting.
     *
     * @return array<string, array{0: string}>
     */
    public static function scenarioIdProvider(): array
    {
        $service = new ScenarioService(
            (new self('scenarioIdProvider'))->fetchTable('Metrics'),
            (new self('scenarioIdProvider'))->fetchTable('Alerts'),
        );

        $cases = [];
        foreach (array_keys($service->getScenarios()) as $id) {
            $cases[$id] = [$id];
        }

        return $cases;
    }

    /**
     * Each catalogue entry must carry every key the UI and these tests rely on.
     *
     * @param string $id The scenario id under test.
     * @return void
     */
    #[DataProvider('scenarioIdProvider')]
    public function testCatalogueEntryIsComplete(string $id): void
    {
        $scenario = $this->service->getScenarios()[$id];

        foreach (['id', 'name', 'description', 'expected_outcome', 'expected_alerts', 'source', 'tags', 'events'] as $key) {
            $this->assertArrayHasKey($key, $scenario, sprintf('%s is missing the "%s" key.', $id, $key));
        }

        $this->assertSame($id, $scenario['id'], 'The id field must match the catalogue key.');
        $this->assertNotEmpty($scenario['events'], sprintf('%s defines no metric events.', $id));
        $this->assertIsInt($scenario['expected_alerts'], 'expected_alerts must be an integer to be assertable.');

        foreach ($scenario['events'] as $event) {
            $this->assertArrayHasKey('name', $event);
            $this->assertArrayHasKey('value', $event);
            $this->assertArrayHasKey('unit', $event);
        }
    }

    /**
     * Running a scenario must raise exactly the number of alerts it claims.
     *
     * This is the drift catcher. Change a threshold in AlertsService, or a value
     * in a scenario, and whichever of the two is now wrong shows up here instead
     * of in a stale sentence on the dashboard.
     *
     * @param string $id The scenario id under test.
     * @return void
     */
    #[DataProvider('scenarioIdProvider')]
    public function testScenarioRaisesTheAlertsItClaims(string $id): void
    {
        $scenario = $this->service->getScenarios()[$id];

        $this->service->run($id);

        $this->assertSame(
            $scenario['expected_alerts'],
            $this->alertsTable->find()->count(),
            sprintf(
                '%s claims "%s" but raised a different number of alerts.',
                $id,
                (string)$scenario['expected_outcome'],
            ),
        );
    }

    /**
     * The failure mode each scenario demonstrates, as a phrase its diagnosis must contain.
     *
     * Chosen to be the discriminating part of the diagnosis rather than incidental
     * wording: 'stalled' versus 'saturated' is the whole point of scenarios 1 and
     * 2 sharing a symptom, and the rejection codes are what make 4 and 5 different
     * problems rather than one problem twice.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function failureModeProvider(): array
    {
        return [
            'stalled channel, no internal cause' => ['scenario-1', 'the stall is on the channel'],
            'lag explained by saturation' => ['scenario-2', 'Infrastructure is saturated'],
            'healthy flow' => ['scenario-3', 'receipts are coming back'],
            'expired certificate names 00100' => ['scenario-4', '00100'],
            'payload refused, certificate valid' => ['scenario-5', '00200'],
            'certificate expiring, nothing broken' => ['scenario-6', 'expires in 5'],
        ];
    }

    /**
     * A scenario must produce the diagnosis its description promises.
     *
     * Without this, a scenario can raise the right number of alerts for entirely
     * the wrong reason — which is what the previous catalogue did, describing an
     * expired-certificate incident while emitting memory usage readings.
     *
     * @param string $id The scenario id under test.
     * @param string $expectedPhrase Discriminating phrase the diagnosis must contain.
     * @return void
     */
    #[DataProvider('failureModeProvider')]
    public function testScenarioProducesItsFailureMode(string $id, string $expectedPhrase): void
    {
        $this->service->run($id);

        $diagnosis = (new AiDiagnosticsService($this->metricsTable, $this->alertsTable))
            ->diagnose('test-scenario-' . $id)
            ->diagnosis;

        $this->assertStringContainsStringIgnoringCase(
            $expectedPhrase,
            $diagnosis,
            sprintf('%s must be diagnosed as the failure mode it describes.', $id),
        );
    }

    /**
     * Dry-run must report what would happen without writing anything.
     *
     * The simulator exists to be run against real deployments, so the promise
     * that dry-run touches nothing has to hold — verified on the scenario with
     * the most events, since a partial write would be easiest to miss there.
     *
     * @return void
     */
    public function testDryRunPersistsNothing(): void
    {
        $result = $this->service->run('scenario-1', true);

        $this->assertSame(0, $this->metricsTable->find()->count(), 'Dry-run must not insert metrics.');
        $this->assertSame(0, $this->alertsTable->find()->count(), 'Dry-run must not insert alerts.');
        $this->assertNotEmpty($result->log, 'Dry-run must still report what it would have done.');
    }

    /**
     * An unknown scenario id must be rejected, and the message must list the valid ones.
     *
     * The catalogue is not user-extensible, so an unknown id is a programming
     * error or a stale link; naming the alternatives turns it into a two-second
     * fix instead of a hunt through the source.
     *
     * @return void
     */
    public function testUnknownScenarioIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/scenario-1/');

        $this->service->run('scenario-does-not-exist');
    }
}
