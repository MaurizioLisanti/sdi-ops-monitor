## HANDOFF_m2_scenario_simulator.md

### Metadata
- task: TASK_m2_scenario_simulator
- status: DONE
- correlation_id: f3a8c2e1-7b4d-4f9a-8c6e-2d1a0b5c8e9f
- run_id: run-20260410-001
- created: 2026-04-10T16:17:00Z
- branch: task/m2_scenario_simulator
- agent: claude-sonnet-4-6

### Summary
Implemented the SDI/FatturaPA Scenario Simulator: `ScenarioService` with 4 predefined operational scenarios (Milan CPU spike, Rome memory pressure, Turin normal operation, Naples batch failure spike) using realistic Italian site names and SDI error codes (003, 004, 009). `ScenarioSimulatorController` exposes `GET /simulate` (form) and `POST /simulate/run` (execution with optional dry-run). Bootstrap 5 UI with scenario cards, execution log, alert severity badges, and Log Viewer deep-link via correlation ID. 4 integration tests + full suite 30/30 PASS.

### Files changed
- `src/Service/ScenarioService.php` — aggiunto (ScenarioResult readonly class + ScenarioService con 4 scenari, UUID v4 generation, AlertsService integration, dry-run support)
- `src/Controller/ScenarioSimulatorController.php` — aggiunto (index() GET + run() POST, validazione scenario_id, 422 su input non valido)
- `templates/ScenarioSimulator/index.php` — aggiunto (scenario selection cards, CSRF hidden field, dry-run checkbox, Bootstrap 5)
- `templates/ScenarioSimulator/run.php` — aggiunto (metrics table, alerts table con severity badges, execution log panel, Log Viewer deep-link, DRY RUN banner)
- `src/Application.php` — modificato (aggiunte route /simulate/run e /simulate)
- `tests/TestCase/Controller/ScenarioSimulatorControllerTest.php` — aggiunto (4 integration tests)
- `coord/STATE.json` — modificato (TASK_m2_scenario_simulator → DONE, M2 6/6 DONE → 100%)

### Commands run
```
php8.2 -l src/Service/ScenarioService.php                                          → PASS
php8.2 -l src/Controller/ScenarioSimulatorController.php                           → PASS
php8.2 -l templates/ScenarioSimulator/index.php                                    → PASS
php8.2 -l templates/ScenarioSimulator/run.php                                      → PASS
php8.2 -l src/Application.php                                                      → PASS
php8.2 -l tests/TestCase/Controller/ScenarioSimulatorControllerTest.php            → PASS
php8.2 vendor/bin/phpunit tests/TestCase/Controller/ScenarioSimulatorControllerTest.php --testdox → PASS (4 tests, 15 assertions)
make test                                                                           → PASS (30 tests, 87 assertions)
```

### Assunzioni fatte
- [A1] `ScenarioResult` è definita nello stesso file di `ScenarioService.php` (unico allowed path in `src/Service/`). PSR-4 autoloading funziona perché `ScenarioService` è sempre caricata prima di `ScenarioResult` tramite il controller — nessuna referenza standalone a `App\Service\ScenarioResult`.
- [A2] Soglie AlertsService (DEFAULT_THRESHOLDS): cpu_usage high≥80/critical≥95, memory_usage high≥85/critical≥95. I valori degli scenari sono calibrati per produrre esattamente il numero di alert dichiarato in `expected_outcome`.
- [A3] La route `/simulate/run` è registrata PRIMA di `/simulate` in Application::routes() per evitare che il router interpreti `run` come un segmento wildcard del path `/simulate`. Con route esplicite (non `connect('/simulate/*')`) l'ordine non è critico, ma è stata adottata la sequenza più specifica → meno specifica per chiarezza.
- [A4] Il CSRF token nella form usa `$this->request->getAttribute('csrfToken')` in plain PHP — compatibile con CsrfProtectionMiddleware senza usare FormHelper. In test, `enableCsrfToken()` gestisce cookie + campo automaticamente.
- [A5] `testRunReturns200WithValidScenario` usa scenario-3 (0 alert, 3 metriche) per minimizzare side-effects in DB durante i test.
- [A6] `dry_run` è trattato come truthy quando la stringa è esattamente `'1'` (valore del checkbox HTML). Qualsiasi altro valore (assente, `'0'`, `''`) → dry_run=false.
- [A7] I tag SDI: `sdi_error => null` per scenario-3 (nessun codice errore in operazione normale) — il template verifica con `!empty()` prima di mostrare il badge.

### Rischi / TODO residui
- [RISK-1] `ScenarioResult` nello stesso file di `ScenarioService` — se in futuro il Planner vuole usare `ScenarioResult` stand-alone (senza caricare `ScenarioService`), sarà necessario creare `src/Service/ScenarioResult.php` separato (Allowed Path nel TASK_fix dedicato).
- [RISK-2] I dati inseriti dal simulatore in ambienti prod sono reali Metric + Alert records. Il DoD prevede che l'operatore usi dry-run o cleanup manuale post-demo. Non è implementato un meccanismo di rollback automatico — task futuro.
- [TODO-1] Il check "✓ matches expected" nel `run.php` usa un regex semplice (`preg_replace('/[^0-9]/', '', ...)`) per estrarre il numero atteso da `expected_outcome`. Funziona con le 4 stringhe attuali; se il catalogo cambia il formato, il check va aggiornato.
- [TODO-2] Wave 3 completata — M2 è al 100%. Il Planner può pianificare M3 o dichiarare il progetto prod-ready.
