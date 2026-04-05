## HANDOFF_m1_auth.md

### Metadata
- task: TASK_m1_auth
- status: DONE
- correlation_id: a7f3c2d1-9b4e-4f8a-b6c5-e2d1f0a9b8c7
- run_id: run-20260405-001
- created: 2026-04-05T00:00:00Z
- branch: task/m1_auth
- agent: claude-sonnet-4-6

### Summary
HTTP Basic Authentication middleware implemented and registered in the CakePHP 5
middleware stack. `GET /` and `POST /api/metrics` are now protected by
`BasicAuthMiddleware`; `GET /health` remains unconditionally exempt for AWS
liveness probes.

### Files changed
- `src/Middleware/BasicAuthMiddleware.php` — added: PSR-15 middleware; reads `APP_AUTH_USER` / `APP_AUTH_PASSWORD` from env; exempts `/health`; uses `hash_equals()` for timing-safe comparison; logs auth failures as structured JSON with `correlation_id`
- `src/Application.php` — modified: registered `BasicAuthMiddleware` after `RoutingMiddleware` (post-routing placement as specified); added `use` import
- `config/app_local.php.example` — modified: added documentation block for `APP_AUTH_USER` and `APP_AUTH_PASSWORD` env vars with fail-closed behaviour note
- `tests/TestCase/Middleware/BasicAuthMiddlewareTest.php` — added: 4 unit tests covering 401 without credentials, 200 with valid credentials, /health exempt, 401 on wrong credentials
- `tests/TestCase/Controller/DashboardControllerTest.php` — modified: added `setUp`/`tearDown` for env credential injection; `configRequest` injects `Authorization` header
- `tests/TestCase/Controller/MetricsControllerTest.php` — modified: added `setUp`/`tearDown` for env credential injection; `configRequest` injects `Authorization` header in both test methods
- `coord/STATE.json` — modified: `TASK_m1_auth` status BLOCKED → DONE, handoff pointer set, M1 milestone progress updated to 60%

### Commands run
```
php8.2 -l src/Middleware/BasicAuthMiddleware.php  → PASS — No syntax errors detected
php8.2 -l src/Application.php                     → PASS — No syntax errors detected
php8.2 vendor/bin/phpunit tests/TestCase/Middleware/BasicAuthMiddlewareTest.php → PASS — OK (4 tests, 7 assertions)
make test                                          → PASS — OK (14 tests, 41 assertions)
```

### Assunzioni fatte
- [A3-updated] Test existing in DashboardControllerTest and MetricsControllerTest have been updated to supply Basic Auth credentials via `configRequest()`, as anticipated by task assumption A3.
- [A14-applied] `hash_equals()` applied to both username and password comparisons independently; both must match for authentication to succeed.
- [A15-applied] Test credentials injected via `putenv()` and `$_ENV` in `setUp()` and cleared in `tearDown()` to prevent cross-test contamination.
- [A16] `BasicAuthMiddleware` is placed between `RoutingMiddleware` and `BodyParserMiddleware` — after routing (URI path is available from the raw request URI regardless, but placement is consistent with the TASK spec) and before CSRF so unauthenticated POSTs receive 401, not a CSRF error.
- [A17] The TASK lists 3 test methods (`testDashboardReturns401WithoutCredentials`, `testDashboardReturns200WithValidCredentials`, `testHealthIsExemptFromAuth`) but a 4th (`testWrongCredentialsReturn401`) was added to cover the explicit DoD criterion "Credenziali errate → 401 (non 403)". This is within the allowed test path and strengthens coverage.

### Rischi / TODO residui
- [RISK-1] `env()` in CakePHP reads from `getenv()` then `$_ENV` then `$_SERVER`. In Docker/ECS environments where env vars are injected before PHP starts, `getenv()` is the primary path. In the test suite `putenv()` + `$_ENV` covers both paths. No action needed now; verify in integration smoke test post-merge.
- [RISK-2] If `APP_AUTH_USER` or `APP_AUTH_PASSWORD` is not set in the production deployment the middleware will deny ALL requests including `POST /api/metrics`. This fail-closed behaviour is intentional (TASK A13) — coordinate with Ops to ensure vars are configured before deploy.
- [TODO-1] `GET /health` exemption is path-based on the raw URI. If CakePHP ever rewrites the path via `AssetMiddleware` before `BasicAuthMiddleware` is reached, verify exemption still works. Current middleware order ensures this cannot happen (Asset runs before Routing and before BasicAuth).
- [TODO-2] `POST /api/metrics` CSRF: BasicAuth is placed before `CsrfProtectionMiddleware` in the stack. Unauthenticated POSTs correctly receive 401. Authenticated POSTs still require CSRF token (handled by `enableCsrfToken()` in tests). No change needed.
