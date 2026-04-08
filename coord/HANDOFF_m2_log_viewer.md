## HANDOFF_m2_log_viewer.md

### Metadata
- task: TASK_m2_log_viewer
- status: DONE
- correlation_id: a7f3c2d1-9b4e-4f2a-8c1d-e5f6a7b8c9d0
- run_id: run-20260408-001
- created: 2026-04-08T16:32:00Z
- branch: task/m2_log_viewer
- agent: claude-sonnet-4-6

### Summary
Implemented the Log Viewer web UI (`GET /logs`) with BasicAuth protection, Bootstrap 5 table with level badges and correlation ID column, JSON line-by-line parsing with graceful fallback for non-JSON lines, and level/correlation_id/lines query-string filters. Route registered via `Application::routes()` override. 3 controller integration tests PASS; full suite 26/26 PASS.

### Files changed
- `src/Controller/LogViewerController.php` — aggiunto (nuovo controller con PHPDoc completo)
- `templates/LogViewer/index.php` — aggiunto (Bootstrap 5 table, filter form, quick links)
- `src/Application.php` — modificato (aggiunto `use RouteBuilder;` + override `routes()` per `/logs`)
- `tests/TestCase/Controller/LogViewerControllerTest.php` — aggiunto (3 integration test)
- `coord/STATE.json` — modificato (TASK_m2_log_viewer → DONE, M2 5/6)

### Commands run
```
php8.2 -l src/Controller/LogViewerController.php → PASS
php8.2 -l templates/LogViewer/index.php          → PASS
php8.2 -l src/Application.php                    → PASS
php8.2 -l tests/TestCase/Controller/LogViewerControllerTest.php → PASS
php8.2 vendor/bin/phpunit tests/TestCase/Controller/LogViewerControllerTest.php --testdox → PASS (3 tests, 6 assertions)
make test → PASS (26 tests, 72 assertions)
```

### Assunzioni fatte
- [A1] Route aggiunta con override `Application::routes()` invece che in `config/routes.php`. Il TASK specifica `src/Application.php` come Allowed Path e come target della route — il metodo `routes()` è il punto corretto in CakePHP 5 per estendere `config/routes.php` senza modificarlo. `config/routes.php` non è stato toccato.
- [A2] `TASK_m2_fix_log_consistency` (DONE) garantisce che le righe in `app.log` siano JSON valido. Il controller gestisce comunque righe non-JSON come entry `raw` per robustezza.
- [A3] Il filtro `?lines=N` è cappato a MAX_LINES=1000 per prevenire OOM su file di log grandi (TASK A4).
- [A4] La route `/logs` non richiede `_method` restriction perché è GET-only per design e il controller non ha handler POST/PUT. Il CsrfProtectionMiddleware non valida token sulle GET per RFC 7231.
- [A5] Il test `testIndexHandlesMissingLogFile` rinomina temporaneamente `logs/app.log` in `.test_bak` se esiste, e lo ripristina in `tearDown()`. Questo è preferibile rispetto ad usare un path fittizio perché il path è hardcoded nel controller (requisito sicurezza TASK A5).
- [A6] Entries mostrate in ordine reverse-cronologico (newest first) per facilitare il triage degli eventi recenti — il TASK non specifica l'ordine ma questo è il comportamento atteso in un log viewer SRE.

### Rischi / TODO residui
- [RISK-1] `readLastLines()` carica l'intero file in memoria via `file()`. Per file di log > 500 MiB questo potrebbe causare pressure di memoria. Con MAX_LINES=1000 e log strutturati (~200 byte/riga) il rischio è contenuto; un approccio streaming (`SplFileObject::seek`) sarebbe più sicuro per volume elevatissimo.
- [RISK-2] Il layout `templates/layout/default.php` non ha un link a `/logs` nella navbar (path non in Allowed Paths). Gli operatori devono navigare manualmente a `/logs`. Da aggiungere in un task dedicato UI.
- [TODO-1] Download/export del log in formato CSV/JSON è nel Non-scope — task futuro.
- [TODO-2] `TASK_m2_scenario_simulator` ora sbloccato (entrambi TASK_m2_sqs_scheduler e TASK_m2_log_viewer DONE).
