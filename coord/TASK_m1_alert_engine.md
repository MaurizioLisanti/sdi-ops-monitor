# TASK_m1_alert_engine — Alert threshold engine

<!-- [UPDATED: 2026-04-03 — Planner pass: ex-skeleton BLOCKED, ora TODO con campi completi] -->

---

## Metadata

```
created:   2026-04-02T00:00:00Z
updated:   2026-04-03T16:00:00Z
assignee:  Executor
status:    TODO
wave:      2
milestone: M1
risk_tier: HIGH
```

---

## Obiettivo

Implementare `AlertsService::evaluate()` che, dopo ogni salvataggio di una `Metric`,
confronta il valore con soglie configurabili e crea un `Alert` con la severity
appropriata se la soglia è violata. `MetricsController::add()` chiama il servizio
dopo il save con esito 201.

---

## Scope

- [x] `src/Service/AlertsService.php` — nuovo: `evaluate(Metric $metric): ?Alert`
      - Legge soglie da `Configure::read('Thresholds')` (configurabile in app_local.php)
      - Soglie default hardcoded come fallback:
        `cpu_usage` → warning ≥ 80 (severity: high), critical ≥ 95 (severity: critical)
        `memory_usage` → warning ≥ 85, critical ≥ 95
        Metriche senza soglia → nessun alert
      - Crea Alert via AlertsTable::save() se soglia violata
      - Log strutturato JSON del risultato (usa correlation_id se disponibile da request)
- [x] `src/Controller/Api/MetricsController.php` — modifica: chiama
      `AlertsService::evaluate($metric)` dopo save riuscito; eventuali errori
      del servizio non fanno fallire il 201 (best-effort — log ERROR)
- [x] `src/Model/Table/AlertsTable.php` — modifica: `findOpen()` usa
      `FIELD(severity, 'critical','high','medium','low')` per ordinamento semantico
      (fix carry da M0 — risolve [A_SEVERITY])
- [x] `tests/TestCase/Service/AlertsServiceTest.php` — nuovo:
      `testEvaluateCreatesAlertWhenThresholdExceeded`,
      `testEvaluateSkipsAlertBelowThreshold`,
      `testEvaluateReturnsNullForUnknownMetric`

## Non-scope

- [ ] NON implementare UI configurazione soglie (M2)
- [ ] NON implementare migration per tabella thresholds (M2)
- [ ] NON implementare notifiche push/email (M2)
- [ ] NON modificare AlertsTable association o schema DB
- [ ] NON implementare acknowledge action (M1+ — definire in TASK separato)

---

## Risk tier

**HIGH** — scrive dati in produzione (Alert entities), modifica MetricsController
su path critico (POST /api/metrics). Failure del servizio NON deve bloccare il 201.
Richiede test su error path (service throws → 201 comunque).

---

## Allowed paths

```
src/Service/AlertsService.php
src/Controller/Api/MetricsController.php
src/Model/Table/AlertsTable.php
src/Model/Entity/Alert.php
tests/TestCase/Service/AlertsServiceTest.php
tests/TestCase/Controller/MetricsControllerTest.php
```

## Forbidden paths

```
src/Application.php               # gestito da TASK_m1_observability e _auth
config/Migrations/                # NON modificare schema — soglie in Configure
src/Controller/DashboardController.php
coord/                            # solo Planner/Reviewer
```

---

## Dipendenze

```
BLOCKED_BY: TASK_m1_observability
BLOCKS:     TASK_m1_aws_integration
            TASK_m1_tests_m1

Pre-check:  TASK_m1_observability DONE? NO → BLOCKED
                                        SÌ → pronto per assegnazione

Nota: parallelo con TASK_m1_auth dopo merge di TASK_m1_observability
      (path disgiunti: Service/ vs Middleware/ — nessun overlap).
```

---

## DoD

```bash
# POST metrica sopra soglia → alert creato nel DB
curl -s -X POST http://localhost:8080/api/metrics.json \
  -H "Content-Type: application/json" \
  -d '{"source":"test","name":"cpu_usage","value":96,"unit":"percent","recorded_at":"2026-04-03T10:00:00Z"}'
# → 201 {"id":<N>}

# Verifica alert creato
php8.2 bin/cake.php console  # oppure query diretta su DB test

# Suite completa PASS
make test
# → OK (N tests, N assertions) — exit 0
```

**Criteri DONE:**
- [ ] `POST /api/metrics` con `cpu_usage=96` → crea `Alert` con `severity='critical'`
- [ ] `POST /api/metrics` con `cpu_usage=60` → nessun `Alert` creato
- [ ] Errore in `AlertsService::evaluate()` → risposta rimane 201 (degraded graceful)
- [ ] `findOpen()` ordina severity in modo semantico (critical > high > medium > low)
- [ ] `AlertsServiceTest` → 3 test PASS
- [ ] `make test` → exit 0
- [ ] `coord/HANDOFF_m1_alert_engine.md` creato con `correlation_id`

---

## Comandi verifica (stack-specifici)

```bash
# Linting
php8.2 -l src/Service/AlertsService.php
php8.2 -l src/Controller/Api/MetricsController.php
php8.2 -l src/Model/Table/AlertsTable.php

# Test servizio isolato
php8.2 vendor/bin/phpunit tests/TestCase/Service/AlertsServiceTest.php
# → OK (3 tests, N assertions)

# Suite completa
make test
```

---

## Assunzioni

- [A1] MySQL 8.0 supporta la funzione FIELD() — confermato per il fix severity ordering
- [A9] PHP 8.2 runtime (Makefile: PHP := php8.2)
- [A11] Le soglie default (cpu_usage ≥ 80 high, ≥ 95 critical) sono ragionevoli
        come fallback; l'operatore può sovrascriverle in config/app_local.php
        sotto la chiave 'Thresholds'
- [A12] AlertsService non deve bloccare la response 201 in caso di errore
        (principio: metric ingestion è più critica dell'alert creation)
