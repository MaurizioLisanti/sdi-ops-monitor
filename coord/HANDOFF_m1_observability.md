# HANDOFF_m1_observability

```
task:           TASK_m1_observability
status:         DONE
correlation_id: 08db6d35-fef2-4d9f-85ac-ebfa24437830
run_id:         run-20260403-001
created:        2026-04-04T00:00:00Z
branch:         task/m1_observability
agent:          claude-sonnet-4-6
```

---

## Status

**DONE** — 2026-04-04T00:00:00Z

```
make test → OK (7 tests, 26 assertions) — exit 0
```

---

## Task summary

Implemented `CorrelationIdMiddleware` for structured JSON logging with correlation
ID propagation. All DoD criteria satisfied.

---

## Files changed

| File | Action |
|------|--------|
| `src/Middleware/CorrelationIdMiddleware.php` | **new** — PSR-15 middleware |
| `src/Application.php` | **modified** — added `use` import + registered middleware first in queue |
| `config/app_local.php.example` | **modified** — added `Log` config with `JsonFormatter` |
| `tests/TestCase/Middleware/CorrelationIdMiddlewareTest.php` | **new** — 2 tests, 7 assertions |

---

## Implementation notes

### CorrelationIdMiddleware

- Reads `X-Correlation-ID` request header; echoes it unchanged if present.
- Generates UUID v4 via `random_bytes(16)` + `sprintf()` (RFC 4122 variant 1)
  when header is absent. `ramsey/uuid` not required — no new dependencies added.
- Stores resolved ID as request attribute `correlation_id` for downstream use:
  ```php
  $correlationId = $this->request->getAttribute('correlation_id');
  ```
- Injects `X-Correlation-ID` header into every response (DoD verified via curl).

### Application.php

`CorrelationIdMiddleware` is registered **before** `ErrorHandlerMiddleware` so
the correlation ID is available even when an exception page is rendered.

### app_local.php.example

`Log` config keys `default` and `error` added with `JsonFormatter`. Each log
line is a single-line JSON object. Consumers should pass `correlation_id` in the
context array:
```php
Log::info('Metric ingested', ['correlation_id' => $request->getAttribute('correlation_id'), ...]);
```
This makes every log entry filterable in Kibana by `correlation_id`.

---

## DoD checklist

- [x] `GET /health` responds with `X-Correlation-ID` header
- [x] Client-supplied `X-Correlation-ID` is echoed back unchanged
- [x] Missing `X-Correlation-ID` triggers UUID v4 generation (verified by regex in test)
- [x] `make test` → exit 0 (7 tests, 26 assertions)
- [x] `coord/HANDOFF_m1_observability.md` created with `correlation_id`

---

## Unblocked tasks

- `TASK_m1_alert_engine` — BLOCKED → ready
- `TASK_m1_auth` — BLOCKED → ready

---

## Risks / open items

None. Risk tier MED was mitigated by placing the middleware first (before
ErrorHandler) and verifying the full suite still passes.

---

## Assumptions confirmed

- **A1** — CakePHP 5 `MiddlewareQueue` accepts PSR-15 `MiddlewareInterface` directly. ✓
- **A9** — PHP 8.2 runtime confirmed (`random_bytes` available). ✓
- **A10** — `ramsey/uuid` absent from `composer.json`; used `random_bytes` fallback. ✓
