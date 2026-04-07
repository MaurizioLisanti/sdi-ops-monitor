## HANDOFF_m1_tests_m1.md

### Metadata
- task: TASK_m1_tests_m1
- status: DONE
- correlation_id: 5f85addb-3fd4-40e5-88e7-14850c139424
- run_id: run-20260406-002
- created: 2026-04-06T00:00:00Z
- branch: task/m1_tests_m1
- agent: claude-sonnet-4-6

### Summary

All 17 M1 tests were already passing. The only gap against the DoD scope table was
two method names in `CorrelationIdMiddlewareTest`: renamed to `testHeaderPropagated`
and `testHeaderGeneratedIfAbsent` to match the required names exactly. Zero deprecations.

### Files changed
- tests/TestCase/Middleware/CorrelationIdMiddlewareTest.php — modified (2 method renames)

### Commands run
```
php8.2 -l tests/TestCase/Middleware/CorrelationIdMiddlewareTest.php
→ PASS — No syntax errors detected

php8.2 vendor/bin/phpunit tests/TestCase/Middleware/ tests/TestCase/Service/
→ PASS — OK (9 tests, 26 assertions)

make test
→ PASS — OK (17 tests, 48 assertions) — exit 0

php8.2 vendor/bin/phpunit --testdox
→ PASS — all 17 tests ✔, 0 ✘, 0 skipped

make test 2>&1 | grep -i deprecat
→ PASS — 0 deprecation warnings
```

### Assunzioni fatte
- [A1] The previously written test files (BasicAuthMiddlewareTest, CorrelationIdMiddlewareTest,
  AlertsServiceTest, DashboardControllerTest, MetricsControllerTest, SnsSignatureValidatorTest)
  were already complete and passing on main. Only the method name alignment was missing.
- [A2] `tests/Fixture/AlertsFixture.php` was not created — per assumption A8 in the TASK,
  the fixture is not needed because `AlertsServiceTest` manages its own data lifecycle
  via `deleteAll([])` in `setUp()` and the assertions use the returned entity IDs directly.
- [A3] `phpunit.xml.dist` required no changes — the existing configuration already covers
  all test suites with zero deprecations on PHP 8.2 / PHPUnit 10.5.

### Rischi / TODO residui
- None. All DoD criteria are satisfied:
  - All 11 M1 test methods from the scope table: PASS ✔
  - M0 controller tests updated for auth (already done in prior tasks): PASS ✔
  - `make test` → exit 0, zero deprecations ✔
  - No real AWS network calls in any test ✔
  - Test DB `sdi_ops_monitor_test` used (not production) ✔
