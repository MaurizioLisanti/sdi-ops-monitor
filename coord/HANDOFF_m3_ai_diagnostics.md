## HANDOFF_m3_ai_diagnostics.md

### Metadata
- task: TASK_m3_ai_diagnostics
- status: DONE
- correlation_id: f3a8d2c1-5e7b-4f9a-a0b1-c2d3e4f5a6b7
- run_id: run-20260410-002
- created: 2026-04-10T17:35:00Z
- branch: main
- agent: claude-sonnet-4-6

### Summary
Implemented `GET /ai-diagnostics` endpoint: `AiDiagnosticsService` calls OpenRouter
API (timeout 5 s) and falls back to a rule-based engine when `OPENROUTER_API_KEY` is
absent or the API fails. `AiDiagnosticsController` propagates `correlation_id` through
all audit log entries. 5 new tests added (3 service + 2 controller); make test 35/35 PASS.

### Files changed
- `src/Service/AiDiagnosticsService.php` — added (`DiagnosisResult` value object + service)
- `src/Controller/AiDiagnosticsController.php` — added
- `templates/Pages/ai_diagnostics.php` — added (Bootstrap 5 diagnosis card)
- `tests/TestCase/Service/AiDiagnosticsServiceTest.php` — added (3 tests)
- `tests/TestCase/Controller/AiDiagnosticsControllerTest.php` — added (2 tests)
- `src/Application.php` — modified (added `/ai-diagnostics` route)
- `coord/HANDOFF_m3_ai_diagnostics.md` — added
- `coord/STATE.json` — modified (TASK_m3_ai_diagnostics status → DONE)

### Commands run
```
php8.2 -l src/Service/AiDiagnosticsService.php          → PASS — No syntax errors
php8.2 -l src/Controller/AiDiagnosticsController.php    → PASS — No syntax errors
php8.2 -l tests/TestCase/Service/AiDiagnosticsServiceTest.php     → PASS
php8.2 -l tests/TestCase/Controller/AiDiagnosticsControllerTest.php → PASS

make test                                                → PASS — 35 tests, 106 assertions
  (iteration 1: FAIL — MissingTemplateException: CakePHP combined controller path
   with template name; fix: setTemplatePath('Pages')->setTemplate('ai_diagnostics'))
  (iteration 2: PASS — 35/35)
```

### Assunzioni fatte
- [A1] `OPENROUTER_API_KEY` absent → fallback active. The env var is never
  logged or hardcoded — read only via `env()` at call time.
- [A2] `Cake\Http\Client\Response::__construct(array $headers, string $body)`
  with `['HTTP/1.1 200 OK', ...]` correctly sets status=200 and `isOk()`=true.
  Verified from vendor source at line 128.
- [A3] CakePHP `viewBuilder()->setTemplatePath('Pages')->setTemplate('ai_diagnostics')`
  resolves to `templates/Pages/ai_diagnostics.php` as required by the TASK.
  (Using `setTemplate('Pages/ai_diagnostics')` alone prepends the controller folder.)
- [A4] Alert entity fields used: `severity`, `message`. Field `metric_name` does not
  exist on the Alert entity (not in migration); prompt uses `message` instead.
- [A5] `OPENROUTER_MODEL` env var allows runtime model override; defaults to
  `mistralai/mistral-7b-instruct` (free tier) when absent.

### Rischi / TODO residui
- [R1] `TASK_m3_fix_wave3_obs` is now unblocked — it shares `src/Application.php`.
  The `/ai-diagnostics` route is already registered; fix_wave3_obs must append
  after this line, not replace it.
- [R2] `AiDiagnosticsService::callOpenRouter()` makes a single synchronous HTTP call
  with a 5-second timeout. Under high latency, the page response could be delayed
  up to 5 s. This is acceptable for M3 demo; consider async or caching in future.
- [R3] The deterministic fallback only covers three metric names (`cpu_usage`,
  `memory_usage`, `error_rate`). Unknown metric names are silently skipped.
  Extend `FALLBACK_RULES` when new metric types are ingested.
