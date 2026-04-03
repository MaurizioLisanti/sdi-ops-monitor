## HANDOFF_m0_metric_ingestion.md

### Metadata
- task: TASK_m0_metric_ingestion
- status: DONE
- correlation_id: a2b3c4d5-6e7f-4a8b-9c0d-1e2f3a4b5c6d
- run_id: run-20260403-001
- created: 2026-04-03T00:00:00Z
- branch: task/m0_metric_ingestion
- agent: claude-sonnet-4-6

### Summary
Implemented `MetricsController::add()` using `fetchTable()`/`newEntity()`/`save()`, returning 201 `{"id":<int>}` on success and 422 `{"errors":{...}}` on validation failure. Extended `MetricsTable::validationDefault()` with `requirePresence` for all required fields and mapped the `tags` TEXT column to the `json` ORM type for automatic serialisation. PHPUnit integration tests cover both paths (2 tests, 10 assertions → PASS; suite: 5 tests, 18 assertions → PASS).

### Files changed
- `src/Controller/Api/MetricsController.php` — modificato: rimosso `initialize()`+`loadModel()` (rimosso in CakePHP 5); implementato `add()` con `fetchTable('Metrics')`, `newEntity()`, `save()`, risposta 201/422; PHPDoc su tutti i metodi pubblici
- `src/Model/Table/MetricsTable.php` — modificato: aggiunto `setColumnType('tags', 'json')` in `initialize()`; validazione completa in `validationDefault()` con `requirePresence` per `source`, `name`, `value`, `recorded_at`; PHPDoc su `initialize()` e `validationDefault()`
- `src/Model/Entity/Metric.php` — modificato: PHPDoc classe completo con descrizione campi DB; commento esplicito su `_accessible` (id/created/modified esclusi intenzionalmente)
- `tests/TestCase/Controller/MetricsControllerTest.php` — creato: `testAddReturns201` (201 + id intero, 5 assertions) e `testAddReturns422OnInvalidPayload` (422 + errors.name presente, 5 assertions)

### Commands run
```
php8.2 -l src/Controller/Api/MetricsController.php                 → PASS — No syntax errors
php8.2 -l src/Model/Table/MetricsTable.php                         → PASS — No syntax errors
php8.2 -l src/Model/Entity/Metric.php                              → PASS — No syntax errors
php8.2 -l tests/TestCase/Controller/MetricsControllerTest.php      → PASS — No syntax errors
php8.2 vendor/bin/phpunit tests/.../MetricsControllerTest.php      → PASS — 2 tests, 10 assertions
php8.2 vendor/bin/phpunit                                           → PASS — 5 tests, 18 assertions (suite completa)
make test                                                           → FAIL [ENV_ERROR] — pre-esistente: Makefile usa ./vendor/bin/phpunit (shebang PHP 8.3, no pdo_mysql). Fix tracciato in coord/TASK_fix_makefile_php_prefix.md
```

### Assunzioni fatte
- [A1] MySQL 8.0 up e raggiungibile su 127.0.0.1:3306 (confermato da testAddReturns201 PASS — record scritto e id restituito)
- [A2] `loadModel()` rimosso in CakePHP 5.3 — sostituito con `fetchTable()` come da best practice CakePHP 5 (lo scaffold usava il metodo deprecato/rimosso)
- [A3] `tags` column è TEXT (non JSON MySQL nativo) — `setColumnType('tags', 'json')` istruisce l'ORM a serializzare/deserializzare automaticamente
- [A4] CSRF non è automaticamente bypassata per route API JSON in CakePHP 5 — i test usano `enableCsrfToken()` per iniettare token valido; in produzione serve `skipCheckCallback` in Application.php (fuori Allowed Paths)
- [A5] `recorded_at` inviato come stringa ISO8601/datetime: il type system CakePHP converte automaticamente in `DateTimeImmutable` (datetime column)
- [A6] La PHPUnit deprecation notice (Tests: 5, PHPUnit Deprecations: 1) è ereditata dallo scaffold M0, non introdotta da questo task

### Rischi / TODO residui
- [ENV_ERROR / P:M / I:M] `make test` FAIL pre-esistente — fix tracciato in `coord/TASK_fix_makefile_php_prefix.md`
- [P:L / I:L] CSRF in produzione: `POST /api/metrics` richiede CSRF token finché Application.php non configura `skipCheckCallback`. Da risolvere in un task dedicato (Application.php fuori Allowed Paths di questo task)
- [P:B / I:B] PHPUnit deprecation notice — da risolvere in TASK_m0_tests_smoke

### Se BLOCKED (compila solo se status: BLOCKED)
N/A
