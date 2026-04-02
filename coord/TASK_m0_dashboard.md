# TASK_m0_dashboard — GET / dashboard

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

Implementare `GET /` che restituisce HTTP 200 con una pagina HTML contenente il titolo
"SDI Ops Monitor", il contatore metriche ultime 24h, la lista alert aperti e lo status
generale (verde/giallo/rosso).

---

## Scope

- [x] `DashboardController::index()` carica `$metricsCount` da `MetricsTable` (ultimi 24h)
- [x] `DashboardController::index()` carica `$openAlerts` da `AlertsTable` (status = 'open'), ordinati `severity DESC`
- [x] `$overallStatus` calcolato: `green` se 0 alert, `yellow` se 1–4, `red` se ≥ 5
- [x] Template `templates/Dashboard/index.php` renderizza i dati
- [x] Pagina contiene la stringa "SDI Ops Monitor" nel markup HTML
- [x] `GET /` → HTTP 200

## Non-scope

- [ ] NON implementare grafici o chart in M0 — solo contatori testuali (rinviato a M1)
- [ ] NON scegliere/integrare CSS framework in M0 (Tailwind rimandato a M1 — risolve TODO Planner precedente)
- [ ] NON implementare acknowledge alert in M0 (M1)
- [ ] NON implementare autenticazione (M1)
- [ ] NON modificare migration

---

## Risk tier

**LOW** — endpoint read-only, nessuna scrittura, nessun dato sensibile, nessuna auth.

---

## Allowed paths

```
src/Controller/DashboardController.php
src/Model/Table/MetricsTable.php
src/Model/Table/AlertsTable.php
templates/Dashboard/index.php
templates/layout/default.php
tests/TestCase/Controller/DashboardControllerTest.php
```

## Forbidden paths

```
config/Migrations/   # non modificare schema
src/Controller/Api/MetricsController.php
src/Controller/HealthController.php
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

Nota: TASK_m0_dashboard può partire in parallelo con TASK_m0_health_endpoint
      e TASK_m0_metric_ingestion (path disgiunti — nessun overlap).
```

---

## DoD

```bash
# Risposta HTTP 200 con contenuto atteso
curl -s -w "\nHTTP:%{http_code}" http://localhost:8080/
# → HTML contenente "SDI Ops Monitor"  HTTP:200

# Grep sul body
curl -s http://localhost:8080/ | grep "SDI Ops Monitor"
# → match trovato

# Test PHPUnit
vendor/bin/phpunit tests/TestCase/Controller/DashboardControllerTest.php
# → OK (1 test, N assertions)
```

**Criteri DONE:**
- [ ] `GET /` → 200 con HTML
- [ ] Body HTML contiene "SDI Ops Monitor"
- [ ] `$metricsCount`, `$openAlerts`, `$overallStatus` passati alla view (verificabili nel template)
- [ ] `DashboardControllerTest::testIndexReturns200` PASS
- [ ] `coord/HANDOFF_m0_dashboard.md` creato con `correlation_id`
- [ ] diff summary incluso nel HANDOFF

---

## Comandi verifica (stack-specifici)

```bash
# Verifica variabili view (debug — solo in dev)
curl -s http://localhost:8080/ | grep -E "(metrics_count|open_alerts|overall_status)"

# Suite completa
make test
# → exit 0
```

---

## Assunzioni

- [A1] MySQL 8.0 è up — la query su `metrics` e `alerts` non deve causare 500
- [A3] Nessuna autenticazione in M0 — dashboard accessibile senza token
- [A7] Se `alerts` è vuota (tabella vuota), la dashboard mostra `$overallStatus = 'green'` senza errori
