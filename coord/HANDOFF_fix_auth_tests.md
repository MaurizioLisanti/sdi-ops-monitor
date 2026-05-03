# HANDOFF: Fix Auth Headers in Controller Tests

**Date:** 2026-05-03
**Status:** COMPLETE — no code changes required

---

## Summary

A review was requested to ensure every Controller test that activates
`BasicAuthMiddleware` via `APP_AUTH_USER`/`APP_AUTH_PASSWORD` env vars also
sends a valid `Authorization` header on every HTTP request.

**Outcome:** all six test files were already correct. The full suite runs
clean with no code modifications needed.

---

## Files Audited

| File | `basicAuthHeader()` present | All requests send auth header | 401 tests correctly send no header |
|------|-----------------------------|-------------------------------|--------------------------------------|
| `tests/TestCase/Controller/DashboardControllerTest.php` | ✅ | ✅ | n/a |
| `tests/TestCase/Controller/AiDiagnosticsControllerTest.php` | ✅ | ✅ | ✅ `testGetReturns401WithoutAuth` |
| `tests/TestCase/Controller/LogViewerControllerTest.php` | ✅ | ✅ | ✅ `testIndexReturns401WithoutCredentials` |
| `tests/TestCase/Controller/MetricsControllerTest.php` | ✅ | ✅ (`configureSnsRequest` includes `Authorization`) | n/a |
| `tests/TestCase/Controller/ScenarioSimulatorControllerTest.php` | ✅ | ✅ | n/a |
| `tests/TestCase/Middleware/BasicAuthMiddlewareTest.php` | ✅ (different signature — takes user+password args) | ✅ (unit tests build requests directly) | ✅ `testDashboardReturns401WithoutCredentials` |

---

## `basicAuthHeader()` signature per file

All Controller tests use:
```php
private function basicAuthHeader(): string
{
    return 'Basic ' . base64_encode(self::TEST_USER . ':' . self::TEST_PASSWORD);
}
```

`BasicAuthMiddlewareTest` uses a two-argument variant (appropriate for its
wrong-credentials test case):
```php
private function basicAuthHeader(string $user, string $password): string
{
    return 'Basic ' . base64_encode($user . ':' . $password);
}
```

Both are correct for their respective contexts.

---

## Test Results

```
PHPUnit 10.5.63  PHP 8.3.30
OK (35 tests, 106 assertions)
```

```
vendor/bin/phpcs --standard=CakePHP src/
(no output — zero violations)
```

---

## Acceptance Criteria

| Criterion | Result |
|-----------|--------|
| `vendor/bin/phpunit` → 35 PASS, zero failures | ✅ |
| `vendor/bin/phpcs --standard=CakePHP src/` → PASS | ✅ |
| `coord/HANDOFF_fix_auth_tests.md` produced | ✅ |
