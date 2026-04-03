## HANDOFF_m0_health_endpoint.md

### Metadata
- task: TASK_m0_health_endpoint
- status: DONE
- correlation_id: 7e3f2a1b-4c8e-4a5b-b6c7-d8e9f0a1b2c3
- run_id: run-20260402-002
- created: 2026-04-02T12:00:00Z
- branch: task/m0_health_endpoint
- agent: claude-sonnet-4-6

### Summary
Implemented `HealthController::check()` with a `SELECT 1` ping via `ConnectionManager::get('default')`, returning 200 `{"status":"ok"}` on success and 503 `{"status":"error","detail":"..."}` on failure. Route restricted to GET at routing level with inline CSRF-exemption documentation. PHPUnit integration tests cover both paths (2 tests, 6 assertions → PASS).

### Files changed
- `src/Controller/HealthController.php` — modificato: aggiunto DB ping via `ConnectionManager::execute('SELECT 1')`, gestione eccezione → 503, PHPDoc completo
- `config/routes.php` — modificato: aggiunto `['_method' => 'GET']` per restrizione a routing level, commento CSRF-exemption esplicito
- `tests/TestCase/Controller/HealthControllerTest.php` — modificato: aggiunto `testHealthReturns200` (200 + Content-Type) e `testHealthReturns503WhenDbDown` (simulazione DB down via dropAlias + bad credentials)

### Commands run
```
php -l src/Controller/HealthController.php                    → PASS — No syntax errors
php -l config/routes.php                                      → PASS — No syntax errors
php -l tests/TestCase/Controller/HealthControllerTest.php     → PASS — No syntax errors
php8.2 vendor/bin/phpunit tests/.../HealthControllerTest.php  → PASS — 2 tests, 6 assertions
php8.2 vendor/bin/phpunit                                     → PASS — 3 tests, 8 assertions (suite completa)
make test                                                     → FAIL [ENV_ERROR] — pre-esistente: vendor/bin/phpunit usa shebang PHP 8.3 senza pdo_mysql; il Makefile definisce PHP := php8.2 ma non lo usa nel target test
git checkout main -- coord/STATE.json                         → PASS — revert P0 (Reviewer fix-1): STATE.json ripristinato a versione main
```

### Assunzioni fatte
- [A1] MySQL 8.0 è up e raggiungibile su 127.0.0.1:3306 durante i test (confermato da testHealthReturns200 PASS)
- [A2] `Exception` (base class) è sufficiente per intercettare tutti i tipi di errore DB in M0 (`MissingExtensionException`, `PDOException`, `RuntimeException`)
- [A3] La strategia di simulazione DB-down tramite `ConnectionManager::dropAlias('default') + drop('default') + setConfig(bad_credentials)` è sicura: il finally block ripristina sempre l'alias `test → default` evitando test pollution
- [A4] La PHPUnit deprecation (`Tests: 3, PHPUnit Deprecations: 1`) è ereditata dallo scaffold M0 e non introdotta da questo task

### Rischi / TODO residui
- [ENV_ERROR / P:M / I:M] `make test` fallisce con ENV_ERROR pre-esistente: `Makefile` usa `$(PHPUNIT)` (`./vendor/bin/phpunit`) senza prefissare `$(PHP)` (`php8.2`). Il binary di sistema è PHP 8.3.14 senza `pdo_mysql`. Fix: cambiare `Makefile` riga 38 da `$(PHPUNIT)` a `$(PHP) $(PHPUNIT)`. Richiede un task dedicato (Makefile non in Allowed Paths di questo task).
- [P:B / I:B] La PHPUnit deprecation notice va risolta nel task TASK_m0_tests_smoke

### Se BLOCKED (compila solo se status: BLOCKED)
N/A
