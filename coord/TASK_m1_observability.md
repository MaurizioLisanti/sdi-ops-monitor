# TASK_m1_observability — correlation_id + structured logging

<!-- [UPDATED: 2026-04-03 — Planner pass: nuovo task M1, tutti i campi compilati] -->

---

## Metadata

```
created:   2026-04-03T16:00:00Z
updated:   2026-04-03T16:00:00Z
assignee:  Executor
status:    TODO
wave:      2
milestone: M1
risk_tier: MED
```

---

## Obiettivo

Implementare `CorrelationIdMiddleware` che legge `X-Correlation-ID` dalla request
o ne genera uno UUID v4 nuovo, lo propaga nell'header di risposta e lo rende
disponibile a tutto lo stack applicativo per il logging strutturato JSON.

---

## Scope

- [x] `src/Middleware/CorrelationIdMiddleware.php` — nuovo: legge `X-Correlation-ID`
      dall'header di request; se assente genera UUID v4; inietta nell'header di risposta
- [x] `src/Application.php` — registra `CorrelationIdMiddleware` come primo middleware
      nella coda (prima di `ErrorHandlerMiddleware`)
- [x] `config/app_local.php.example` — aggiunge config log JSON:
      `'Log' => ['className' => 'Cake\Log\Engine\FileLog', 'formatter' => 'json']`
      (commento esplicito: non modificare `config/app_local.php` — file locale)
- [x] `tests/TestCase/Middleware/CorrelationIdMiddlewareTest.php` — nuovo:
      verifica che `X-Correlation-ID` sia presente nella risposta
      (ereditato se già in request; generato se assente)

## Non-scope

- [ ] NON modificare Controller esistenti (MetricsController, HealthController,
      DashboardController) — il correlation_id è disponibile via request attribute
- [ ] NON implementare log aggregation o shipping remoto (M2)
- [ ] NON implementare tracing distribuito OpenTelemetry (M2)
- [ ] NON modificare config/app.php (solo app_local.php.example)

---

## Risk tier

**MED** — modifica Application.php (primo middleware nella catena); un errore
blocca l'intera applicazione. Richiede test su tutte le route esistenti.

---

## Allowed paths

```
src/Middleware/CorrelationIdMiddleware.php
src/Application.php
config/app_local.php.example
tests/TestCase/Middleware/CorrelationIdMiddlewareTest.php
```

## Forbidden paths

```
src/Controller/                   # NON toccare controller esistenti
config/Migrations/                # NON modificare schema
config/app.php                    # NON modificare config globale
coord/                            # solo Planner/Reviewer
```

---

## Dipendenze

```
BLOCKED_BY: N/A  (Wave 1 DONE — pronto per assegnazione)
BLOCKS:     TASK_m1_alert_engine
            TASK_m1_auth

Pre-check:  Wave 1 DONE? SÌ → pronto
```

---

## DoD

```bash
# Verifica header correlation_id presente nella risposta
curl -s -D - http://localhost:8080/health | grep -i "X-Correlation-ID"
# → X-Correlation-ID: <uuid-v4>

# Verifica propagazione di un correlation_id fornito dal client
curl -s -D - -H "X-Correlation-ID: test-uuid-1234" http://localhost:8080/health \
  | grep -i "X-Correlation-ID"
# → X-Correlation-ID: test-uuid-1234

# Suite completa PASS
make test
# → OK (N tests, N assertions) — exit 0
```

**Criteri DONE:**
- [ ] `GET /health` risponde con header `X-Correlation-ID` valorizzato
- [ ] Se il client invia `X-Correlation-ID` nell'header request, lo stesso valore
      viene restituito nell'header response (echo del client ID)
- [ ] Se il client NON invia `X-Correlation-ID`, ne viene generato uno UUID v4
- [ ] `make test` → exit 0
- [ ] `coord/HANDOFF_m1_observability.md` creato con `correlation_id`

---

## Comandi verifica (stack-specifici)

```bash
# Linting PHP
php8.2 -l src/Middleware/CorrelationIdMiddleware.php

# Test middleware isolato
php8.2 vendor/bin/phpunit tests/TestCase/Middleware/CorrelationIdMiddlewareTest.php
# → OK (2 tests, N assertions)

# Suite completa
make test
```

---

## Assunzioni

- [A1] CakePHP 5 MiddlewareQueue supporta middleware custom con interfaccia PSR-15
- [A9] PHP 8.2 è il runtime di riferimento (php8.3 nel container manca pdo_mysql)
- [A10] UUID v4 generato via `\Ramsey\Uuid\Uuid::uuid4()` o `bin2hex(random_bytes(16))`
        con formato standard — verificare se ramsey/uuid è già in composer.json;
        altrimenti usare sprintf() con random_bytes
