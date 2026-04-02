# TASK_m0_tests_smoke — PHPUnit smoke suite

<!-- [UPDATED: 2026-04-02 — Planner pass: aggiunti metadata, allowed paths, BLOCKED_BY/BLOCKS, Non-scope, DoD completo con HANDOFF + correlation_id, comandi verifica stack-specifici] -->

---

## Metadata

```
created:  2026-04-02T00:00:00Z
updated:  2026-04-02T12:00:00Z
assignee: Executor
status:   TODO
wave:     1
milestone: M0
risk_tier: LOW
```

---

## Obiettivo

Completare e far passare la suite PHPUnit di 4 smoke test per i tre endpoint M0.
`make test` deve terminare con exit code 0 e tutti i test PASS.

---

## Scope

- [x] `HealthControllerTest::testHealthReturns200` — GET /health → 200, body `{"status":"ok"}`
- [x] `DashboardControllerTest::testIndexReturns200` — GET / → 200, body contiene "SDI Ops Monitor"
- [x] `MetricsControllerTest::testAddReturns201` — POST /api/metrics con payload valido → 201
- [x] `MetricsControllerTest::testAddReturns422OnInvalidPayload` — POST senza `name` → 422
- [x] Fixture `MetricsFixture` creata se i test di lettura la richiedono (risolve TODO Planner precedente)
- [x] `tests/bootstrap.php` usa DB `sdi_ops_monitor_test`

## Non-scope

- [ ] NON scrivere test per Alert Engine (Wave 2)
- [ ] NON scrivere test di performance/load
- [ ] NON scrivere test per flussi AWS SNS/SQS (M1)
- [ ] NON modificare business logic nei Controller o Model

---

## Risk tier

**LOW** — solo test, nessuna scrittura in produzione, DB di test separato.

---

## Allowed paths

```
tests/TestCase/Controller/HealthControllerTest.php
tests/TestCase/Controller/DashboardControllerTest.php
tests/TestCase/Controller/MetricsControllerTest.php
tests/Fixture/MetricsFixture.php
tests/bootstrap.php
phpunit.xml
phpunit.xml.dist
```

## Forbidden paths

```
src/           # i test non devono modificare il codice sorgente
config/Migrations/
coord/         # solo Planner/Reviewer
```

---

## Dipendenze

```
BLOCKED_BY: TASK_m0_health_endpoint
            TASK_m0_metric_ingestion
            TASK_m0_dashboard
BLOCKS:     N/A  (ultimo task della wave 1)

Pre-check:  TASK_m0_health_endpoint DONE?    NO → BLOCKED
            TASK_m0_metric_ingestion DONE?   NO → BLOCKED
            TASK_m0_dashboard DONE?          NO → BLOCKED
            Tutti e tre DONE → pronto per assegnazione
```

---

## Test da implementare

| Classe | Metodo | Assert |
|--------|--------|--------|
| `HealthControllerTest` | `testHealthReturns200` | HTTP 200, body contiene `"status":"ok"` |
| `DashboardControllerTest` | `testIndexReturns200` | HTTP 200, body contiene "SDI Ops Monitor" |
| `MetricsControllerTest` | `testAddReturns201` | HTTP 201, body contiene `"id"` |
| `MetricsControllerTest` | `testAddReturns422OnInvalidPayload` | HTTP 422, body contiene `"errors"` |

---

## DoD

```bash
# Suite completa PASS
make test
# → OK (4 tests, N assertions)
# → Exit code 0

# Verifica exit code esplicita
vendor/bin/phpunit --testdox
# → ✔ tutti i 4 test verdi
```

**Criteri DONE:**
- [ ] `make test` → exit 0
- [ ] 4 test PASS, 0 FAIL, 0 ERROR
- [ ] DB `sdi_ops_monitor_test` usato (non il DB di produzione)
- [ ] `IntegrationTestTrait` (CakePHP 5) usato in tutti i TestCase
- [ ] `coord/HANDOFF_m0_tests_smoke.md` creato con `correlation_id`
- [ ] diff summary incluso nel HANDOFF

---

## Comandi verifica (stack-specifici)

```bash
# Verifica DB test separato
php -r "define('DS', DIRECTORY_SEPARATOR); require 'tests/bootstrap.php'; echo Cake\Datasource\ConnectionManager::get('test')->config()['database'];"
# → sdi_ops_monitor_test

# Coverage opzionale (non bloccante per DoD)
vendor/bin/phpunit --coverage-text
```

---

## Assunzioni

- [A1] DB `sdi_ops_monitor_test` esiste ed è accessibile durante CI
- [A8] `MetricsFixture` è necessaria solo se `testAddReturns201` verifica la persistenza via SELECT — se si usa solo `assertResponseCode(201)` la fixture non è obbligatoria
