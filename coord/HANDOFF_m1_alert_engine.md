## HANDOFF_m1_alert_engine.md

### Metadata
- task: TASK_m1_alert_engine
- status: DONE
- correlation_id: 07fb3dcf-3145-4619-aa93-9616794580f5
- run_id: run-20260404-001
- created: 2026-04-04T00:30:00Z
- branch: task/m1_alert_engine
- agent: claude-sonnet-4-6

### Summary
Implemented `AlertsService::evaluate()` which reads thresholds from `Configure` (with
built-in fallbacks for `cpu_usage` and `memory_usage`), finds the highest triggered
severity, persists one `Alert` per metric event, and logs every outcome as structured
JSON with correlation_id. MetricsController wraps the call in try/catch so alert
failures never block the 201. `AlertsTable::findOpen()` now uses `FIELD()` for semantic
severity ordering (critical → high → medium → low).

### Files changed
- `src/Service/AlertsService.php` — added (new service class)
- `src/Controller/Api/MetricsController.php` — modified (best-effort AlertsService call after save)
- `src/Model/Table/AlertsTable.php` — modified (findOpen: FIELD() semantic ordering fix)
- `tests/TestCase/Service/AlertsServiceTest.php` — added (3 tests, 8 assertions)

### Commands run
```
php8.2 -l src/Service/AlertsService.php              → PASS
php8.2 -l src/Controller/Api/MetricsController.php   → PASS
php8.2 -l src/Model/Table/AlertsTable.php             → PASS
php8.2 -l tests/TestCase/Service/AlertsServiceTest.php → PASS
php8.2 vendor/bin/phpunit tests/TestCase/Service/AlertsServiceTest.php → PASS (3 tests, 8 assertions)
make test                                             → PASS (10 tests, 34 assertions) exit 0
```

### Assunzioni fatte
- [A1] MySQL 8.0 supporta FIELD() — confermato; query verificata via make test su DB reale.
- [A9] PHP 8.2 runtime (php8.2 nel Makefile).
- [A11] Soglie default `cpu_usage ≥ 80 → high`, `≥ 95 → critical` e `memory_usage ≥ 85 → high`, `≥ 95 → critical` usate come fallback quando `Configure::read('Thresholds')` non è configurato.
- [A12] `AlertsService::evaluate()` è best-effort: qualsiasi eccezione viene loggata (livello error) ma il controller restituisce comunque 201.
- [A13] `metric_id` nel record Alert è nullable — i test non persistono la Metric prima di evaluate(), quindi `metric_id = null` è accettato dalla migration e dalla validazione.
- [A14] `FunctionExpression('FIELD', ['Alerts.severity' => 'identifier', "'critical'", "'high'", "'medium'", "'low'"])` genera correttamente `FIELD(Alerts.severity, 'critical', 'high', 'medium', 'low')` in CakePHP 5 — verificato via make test.

### Rischi / TODO residui
- `AlertsTable::findOpen()` usa `FIELD()` MySQL-specific: se il driver DB cambia (es. SQLite nei test futuri), la query fallirà. Mitigazione: documentare il requisito MySQL 8.0 in SPEC.md se non già presente.
- `AlertsService` è istanziato inline in `MetricsController::add()` — accoppiamento diretto. Per testabilità futura, considerare DI container (CakePHP 5 `services()` in Application.php) in un task dedicato M2.
- I test `AlertsServiceTest` usano il DB reale e chiamano `deleteAll([])` in setUp: se il DB di test non è raggiungibile, i test falliscono con ENV_ERROR anziché IMPL_ERROR.
- `MetricsControllerTest::testAddReturns201` ora triggera implicitamente AlertsService (valore `cpu_usage=87.4 ≥ 80` → crea alert 'high') — effetto collaterale verificato PASS, ma aggiunge un'Alert al DB di test ad ogni run.
