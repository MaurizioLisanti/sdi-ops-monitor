## HANDOFF_m3_fix_wave3_obs.md

### Metadata
- task: TASK_m3_fix_wave3_obs
- status: DONE
- correlation_id: b9e4f1c2-3d5a-4e8b-9f0c-1a2b3c4d5e6f
- run_id: run-20260410-003
- created: 2026-04-10T18:10:00Z
- branch: main
- agent: claude-sonnet-4-6

### Summary
Resolved all 4 non-blocking observations from `coord/INTEGRATION_REPORT_wave3.md`:
updated `routes()` docstring to list all M2/M3 routes (OBS-1), extracted `ScenarioResult`
into `src/Model/ScenarioResult.php` (OBS-2), fixed `fattturapa` typo (OBS-3), and replaced
`file()` with `SplFileObject` + `seek()` in `LogViewerController::readLastLines()` (OBS-4).

### Files changed
- `src/Application.php` — modified (OBS-1: routes() docstring updated to list all 4 routes)
- `src/Model/ScenarioResult.php` — added (OBS-2: ScenarioResult extracted as standalone model)
- `src/Service/ScenarioService.php` — modified (OBS-2: added `use App\Model\ScenarioResult`, removed inline class; OBS-3: fixed typo `fattturapa` → `fatturapa`)
- `src/Controller/LogViewerController.php` — modified (OBS-4: readLastLines() uses SplFileObject + seek())
- `coord/STATE.json` — modified (TASK_m3_fix_wave3_obs status → DONE, M3 percent_done → 60)
- `coord/HANDOFF_m3_fix_wave3_obs.md` — added

### Commands run
```
php8.2 -l src/Application.php                    → PASS — No syntax errors
php8.2 -l src/Service/ScenarioService.php        → PASS — No syntax errors
php8.2 -l src/Model/ScenarioResult.php           → PASS — No syntax errors
php8.2 -l src/Controller/LogViewerController.php → PASS — No syntax errors

make test                                         → PASS — 35 tests, 106 assertions

grep -rn "fattturapa" src/                        → OBS-3 OK (zero results)
test -f src/Model/ScenarioResult.php              → OBS-2 file OK
grep -n "= file(" src/Controller/LogViewerController.php → OBS-4 OK (zero results)
grep "ai-diagnostics" src/Application.php        → OBS-1 OK (route in docstring)
grep "use App\Model\ScenarioResult" src/Service/ScenarioService.php → OBS-2 use OK
```

### Assunzioni fatte
- [A1] `App\Model` namespace is already available in CakePHP 5 autoloading — no additional
       configuration needed for `src/Model/ScenarioResult.php`.
- [A2] `ScenarioSimulatorControllerTest` does not import `ScenarioResult` directly (verified:
       only `IntegrationTestTrait` and `TestCase` are used) — OBS-2 refactor is transparent to tests.
- [A3] `SplFileObject::seek(PHP_INT_MAX)` is the standard pattern for counting file lines without
       loading into memory; available in PHP 8.2 with no additional dependencies.
- [A4] The `readLastLines()` change may return slightly fewer than `$count` non-empty lines when
       the last `$count` raw lines include blank lines — acceptable for a log viewer, behaviour
       is equivalent for typical JSON log files (one entry per line, no blank lines).
- [A5] Typo `fattturapa` in the `source` field of scenario-2 is a display/identifier string only;
       no tests, fixtures, or database rows assert this exact value — fix is safe.

### Rischi / TODO residui
- [R1] `readLastLines()` performs two sequential `SplFileObject` passes over the file (one seek
       to count, one read for content). On extremely large files (> 1 GiB) this is still O(n)
       but memory usage is bounded to O(MAX_LINES). True constant-memory tail would require
       reading the file in reverse using a buffer — not needed for M3 demo scope.
- [R2] `SplFileObject::seek(PHP_INT_MAX)` sets the internal line counter to the last raw line;
       `key() + 1` gives the raw line count including blank lines. If a log file has many
       consecutive blank lines near the end, the returned set may contain fewer than `$count`
       non-empty lines. Document as known limitation if the ops team reports truncated tails.
- [R3] `src/Model/ScenarioResult.php` is a plain value object with no Bake/ORM annotations.
       CakePHP Bake commands should not be run against this file — add to `.cakephp_ignore`
       if the project adopts a bake workflow in future milestones.
