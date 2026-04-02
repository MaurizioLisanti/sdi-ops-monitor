# TASK_m0_metric_ingestion — POST /api/metrics

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
risk_tier: MED
```

---

## Obiettivo

Implementare `POST /api/metrics` che valida il payload JSON, persiste un record `Metric`
in MySQL e risponde HTTP 201 con l'ID creato. Payload non valido → 422 con dettaglio
errori. Latenza p95 < 300ms (da SPEC).

---

## Scope

- [x] `MetricsController::add()` decodifica body JSON, chiama `MetricsTable::newEntity()` + `save()`
- [x] `MetricsTable::validationDefault()` — campi obbligatori: `source`, `name`, `value`, `recorded_at`
- [x] Risposta 201 + `{"id": <int>}` su save OK
- [x] Risposta 422 + `{"errors": {...}}` su validation failure
- [x] `recorded_at` parsato come `DateTimeImmutable`
- [x] `tags` persistito come JSON (campo `tags` nella tabella `metrics`)
- [x] Route `POST /api/metrics` senza CSRF (già in routes.php — API JSON)

## Non-scope

- [ ] NON pubblicare su SNS/SQS dopo il save (M1 scope — risolve TODO Planner precedente)
- [ ] NON implementare autenticazione (M1)
- [ ] NON implementare alert threshold check (M1 — Alert Engine)
- [ ] NON modificare migration già applicate

---

## Risk tier

**MED** — scrittura DB con dati esterni; validazione obbligatoria per prevenire injection
nel campo `name`/`source`. Nessun PII, nessuna auth in M0.

---

## Allowed paths

```
src/Controller/Api/MetricsController.php
src/Model/Table/MetricsTable.php
src/Model/Entity/Metric.php
config/routes.php
tests/TestCase/Controller/MetricsControllerTest.php
```

## Forbidden paths

```
config/Migrations/   # schema già definito — non modificare
src/Controller/HealthController.php
src/Controller/DashboardController.php
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
# Test payload valido → 201
curl -s -w "\nHTTP:%{http_code}" -X POST http://localhost:8080/api/metrics.json \
  -H 'Content-Type: application/json' \
  -d '{"source":"aws-ec2-prod-01","name":"cpu_usage","value":87.4,"unit":"percent","tags":{"env":"prod"},"recorded_at":"2026-04-02T10:00:00Z"}'
# → {"id":1}  HTTP:201

# Test payload non valido → 422
curl -s -w "\nHTTP:%{http_code}" -X POST http://localhost:8080/api/metrics.json \
  -H 'Content-Type: application/json' \
  -d '{"source":"test"}'
# → {"errors":{...}}  HTTP:422

# Test PHPUnit
vendor/bin/phpunit tests/TestCase/Controller/MetricsControllerTest.php
# → OK (2 tests, N assertions)
```

**Criteri DONE:**
- [ ] `POST /api/metrics` con payload valido → 201 `{"id": <int>}`
- [ ] `POST /api/metrics` senza `name` → 422 `{"errors":{...}}`
- [ ] Record verificabile in `SELECT * FROM metrics`
- [ ] `MetricsControllerTest::testAddReturns201` PASS
- [ ] `MetricsControllerTest::testAddReturns422OnInvalidPayload` PASS
- [ ] `coord/HANDOFF_m0_metric_ingestion.md` creato con `correlation_id`
- [ ] diff summary incluso nel HANDOFF

---

## Comandi verifica (stack-specifici)

```bash
# Verifica record persistito in DB
mysql -u<user> -p<pass> sdi_ops_monitor \
  -e "SELECT id, source, name, value, recorded_at FROM metrics ORDER BY id DESC LIMIT 1;"

# Suite completa (non deve rompere altri test)
make test
# → exit 0
```

---

## Assunzioni

- [A1] MySQL 8.0 è up durante il test
- [A3] Nessuna autenticazione in M0 — endpoint accessibile senza token
- [A6] Il campo `tags` nella tabella `metrics` è di tipo `JSON` (o `TEXT` con serializzazione) — verificare migration `CreateMetricsTable`
