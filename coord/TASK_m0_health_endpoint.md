# TASK_m0_health_endpoint — GET /health probe

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

Implementare `GET /health` che risponde `{"status":"ok"}` (HTTP 200) quando l'app è up
e il DB è raggiungibile, oppure `{"status":"error","detail":"..."}` (HTTP 503) se il
ping DB fallisce. Latenza p95 < 500ms.

---

## Scope

- [x] `HealthController::check()` esegue ping DB via `ConnectionManager::get('default')`
- [x] Risposta 200 + `{"status":"ok"}` se ping OK
- [x] Risposta 503 + `{"status":"error","detail":"<messaggio>"}` se ping fallisce
- [x] Header `Content-Type: application/json` in entrambi i casi
- [x] Route `/health` esclusa da CSRF protection in `config/routes.php`

## Non-scope

- [ ] NON implementare check memoria/disk in M0 (rimandato a M1 su richiesta SRE — risolve TODO Planner precedente)
- [ ] NON aggiungere autenticazione (M1)
- [ ] NON toccare MetricsController o DashboardController
- [ ] NON modificare migration

---

## Risk tier

**LOW** — endpoint read-only, nessuna scrittura DB, nessun dato sensibile.

---

## Allowed paths

```
src/Controller/HealthController.php
config/routes.php
tests/TestCase/Controller/HealthControllerTest.php
```

## Forbidden paths

```
config/Migrations/   # solo TASK_scaffold_m0_boot
src/Model/           # non modificare Model in questo task
coord/               # solo Planner/Reviewer
```

---

## Dipendenze

```
BLOCKED_BY: TASK_scaffold_m0_boot
BLOCKS:     TASK_m0_tests_smoke

Pre-check:  TASK_scaffold_m0_boot DONE?
            NO → stato BLOCKED (non avviare)
            SÌ → pronto per assegnazione
```

---

## DoD

```bash
# App deve essere up (make up)
curl -s http://localhost:8080/health
# → HTTP 200
# → body: {"status":"ok"}

# Verifica Content-Type
curl -sI http://localhost:8080/health | grep -i content-type
# → Content-Type: application/json

# Test PHPUnit per questo endpoint
vendor/bin/phpunit tests/TestCase/Controller/HealthControllerTest.php
# → OK (1 test, N assertions)
```

**Criteri DONE:**
- [ ] `GET /health` → 200 `{"status":"ok"}` con DB up
- [ ] `GET /health` → 503 con DB non raggiungibile (testato simulando connessione fallita)
- [ ] `Content-Type: application/json` presente
- [ ] `HealthControllerTest::testHealthReturns200` PASS in `make test`
- [ ] `coord/HANDOFF_m0_health_endpoint.md` creato con `correlation_id`
- [ ] diff summary incluso nel HANDOFF

---

## Comandi verifica (stack-specifici)

```bash
# Latenza (deve essere < 500ms)
time curl -s http://localhost:8080/health
# → real < 0.500s

# Suite completa (non deve rompere altri test)
make test
# → exit 0
```

---

## Assunzioni

- [A1] MySQL 8.0 è up durante il test di integrazione
- [A3] Nessuna autenticazione in M0 — `/health` accessibile senza token
