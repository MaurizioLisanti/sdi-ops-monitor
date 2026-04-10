<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\ScenarioService;

/**
 * ScenarioSimulatorController — SDI/FatturaPA operational scenario simulator UI.
 *
 * Allows SRE operators to inject predefined metric events into the pipeline
 * to verify end-to-end behaviour (metric ingestion → alert evaluation) without
 * sending real HTTP requests to POST /api/metrics.
 *
 * Both routes are protected by BasicAuthMiddleware automatically.
 * Input validation rejects any scenario_id not present in the static catalogue,
 * preventing arbitrary data injection via the form.
 *
 * Routes:
 *   GET  /simulate     → index() — scenario selector form
 *   POST /simulate/run → run()   — execute a scenario and display results
 */
class ScenarioSimulatorController extends AppController
{
    /**
     * Render the scenario selection form.
     *
     * Passes the full scenario catalogue to the view so operators can read the
     * scenario descriptions and expected outcomes before choosing which one to run.
     *
     * @return void CakePHP renders templates/ScenarioSimulator/index.php automatically.
     */
    public function index(): void
    {
        $service = new ScenarioService(
            $this->fetchTable('Metrics'),
            $this->fetchTable('Alerts'),
        );

        $this->set('scenarios', $service->getScenarios());
        // Initialise error to null so the template does not need to check isset().
        $this->set('error', null);
    }

    /**
     * Execute the selected scenario and render the results page.
     *
     * Validates that scenario_id is one of the known catalogue keys; returns HTTP 422
     * with the selection form re-rendered when the value is absent or unrecognised.
     * This prevents arbitrary data injection through the POST body.
     *
     * When dry_run=1 is present in the POST body, ScenarioService skips all database
     * writes and returns a ScenarioResult that describes what would have happened.
     *
     * On success, the view receives a ScenarioResult and the matching scenario
     * definition so the results page can display the expected vs actual outcome.
     *
     * @return void CakePHP renders templates/ScenarioSimulator/run.php on success,
     *              or templates/ScenarioSimulator/index.php on validation failure (HTTP 422).
     * @throws \Cake\Http\Exception\MethodNotAllowedException When the HTTP method is not POST.
     */
    public function run(): void
    {
        $this->request->allowMethod('post');

        $scenarioId = trim((string)($this->request->getData('scenario_id') ?? ''));
        // Treat any truthy non-empty value as dry_run=true (checkbox sends '1').
        $dryRun = (string)($this->request->getData('dry_run') ?? '') === '1';

        $service   = new ScenarioService(
            $this->fetchTable('Metrics'),
            $this->fetchTable('Alerts'),
        );
        $scenarios = $service->getScenarios();

        if ($scenarioId === '' || !isset($scenarios[$scenarioId])) {
            // Invalid or missing scenario — return 422 with the selection form.
            $this->response = $this->response->withStatus(422);
            $this->viewBuilder()->setTemplate('index');
            $this->set([
                'error'     => 'Invalid or missing scenario_id. Please select a scenario from the list.',
                'scenarios' => $scenarios,
            ]);

            return;
        }

        $result = $service->run($scenarioId, $dryRun);

        $this->set([
            'result'   => $result,
            'scenario' => $scenarios[$scenarioId],
        ]);
    }
}
