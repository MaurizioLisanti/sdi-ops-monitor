## HANDOFF_m0_tests_smoke.md

### Metadata
- task: TASK_m0_tests_smoke
- status: DONE
- correlation_id: b4c7d2e1-9f3a-4b8c-a6d5-e0f1a2b3c4d5
- run_id: run-20260403-003
- created: 2026-04-03T15:00:00Z
- branch: task/m0_tests_smoke
- agent: claude-sonnet-4-6

### Summary
All 4 scope smoke tests were already implemented and passing (5 tests total including the 503 error path added in TASK_m0_health_endpoint). Fixed `make test` by prefixing `$(PHP)` in the Makefile so phpunit runs under php8.2 instead of the broken system php 8.3 (no pdo_mysql). Migrated `phpunit.xml.dist` to PHPUnit 10.5 schema, eliminating the recurring deprecation notice. Exit code 0, 0 failures, 0 deprecations.

### Files changed
- `Makefile` — modified: `test` and `test-coverage` targets now invoke `$(PHP) $(PHPUNIT)` instead of bare `$(PHPUNIT)` to force php8.2 interpreter (TASK_fix_makefile_php_prefix absorbed)
- `phpunit.xml.dist` — modified: migrated XML schema from deprecated format to PHPUnit 10.5 (`<coverage>` → `<source>` block, `xsi:noNamespaceSchemaLocation` updated to `https://schema.phpunit.de/10.5/phpunit.xsd`)

### Commands run
```
php8.2 vendor/bin/phpunit --colors=never --testdox           → PASS — 5 tests, 19 assertions, 0 deprecations
make test                                                    → PASS — exit 0 (5 tests, 19 assertions)
```

### Test coverage (testdox)
```
Dashboard Controller
 ✔ Index returns 200

Health Controller
 ✔ Health returns 200
 ✔ Health returns 503 when db down

Metrics Controller
 ✔ Add returns 201
 ✔ Add returns 422 on invalid payload
```

### Assunzioni fatte
- [A1] All 4 TASK scope tests were already implemented in prior tasks (TASK_m0_health_endpoint, TASK_m0_dashboard, TASK_m0_metric_ingestion) — no new test code was needed.
- [A8] MetricsFixture not required: testAddReturns201 verifies HTTP 201 + response body only, without issuing a SELECT query after the insert.
- [A_FIX] Makefile fix (TASK_fix_makefile_php_prefix) absorbed into this task per explicit user authorisation; Makefile is not in the original TASK Allowed Paths but the user extension takes precedence.
- [A_SCHEMA] phpunit.xml.dist schema migration performed with `--migrate-configuration`; the backup file (phpunit.xml.dist.bak) was deleted immediately — it is not source-controlled.

### Rischi / TODO residui
- [P:L / I:L] PHPUnit 10.5 deprecation notice was schema-related and is now resolved; one PHPUnit framework deprecation from CakePHP internals may surface in future PHPUnit upgrades — no action needed in M0.
- [P:L / I:M] Bootstrap 5 CDN in templates/layout/default.php requires internet access in dev — tracked from TASK_m0_dashboard, deferred to M1/M2.
- [P:L / I:L] Severity ordering in findOpen() is alphabetical not semantic — tracked from TASK_m0_dashboard, deferred to M1.
- [P:L / I:L] Wave 1 exit condition requires make test PASS — satisfied. STATE.json wave_1.status should be set to DONE by Planner/Reviewer after merge.

### Se BLOCKED (compila solo se status: BLOCKED)
N/A
