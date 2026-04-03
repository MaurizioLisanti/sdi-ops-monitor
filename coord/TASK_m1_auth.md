# TASK_m1_auth — Basic authentication middleware

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
risk_tier: HIGH
```

---

## Obiettivo

Proteggere `GET /` (dashboard) e `POST /api/metrics` con HTTP Basic Authentication
usando credenziali configurate tramite variabili d'ambiente. `GET /health` rimane
esente per garantire il funzionamento delle liveness probe AWS.

---

## Scope

- [x] `src/Middleware/BasicAuthMiddleware.php` — nuovo: implementa PSR-15
      `MiddlewareInterface`; legge `APP_AUTH_USER` e `APP_AUTH_PASSWORD` da env;
      restituisce 401 con header `WWW-Authenticate: Basic realm="SDI Ops Monitor"`
      se credenziali mancanti o errate; path `/health` sempre esente
- [x] `src/Application.php` — registra `BasicAuthMiddleware` dopo
      `RoutingMiddleware` (deve essere eseguito post-routing per poter leggere
      il path corrente)
- [x] `config/app_local.php.example` — aggiunge commento con variabili richieste:
      `APP_AUTH_USER`, `APP_AUTH_PASSWORD` (non aggiunge valori — solo documentazione)
- [x] `tests/TestCase/Middleware/BasicAuthMiddlewareTest.php` — nuovo:
      `testDashboardReturns401WithoutCredentials`,
      `testDashboardReturns200WithValidCredentials`,
      `testHealthIsExemptFromAuth`

## Non-scope

- [ ] NON implementare gestione utenti (registrazione, cambio password, DB users) — M2
- [ ] NON implementare JWT/OAuth/session-based auth — M2
- [ ] NON implementare RBAC (ruoli e permessi) — M2
- [ ] NON modificare route file (la protezione è nel middleware, non nelle route)
- [ ] NON proteggere asset statici (webroot/) — non applicabile in M1

---

## Risk tier

**HIGH** — modifica Application.php; un errore può rendere inaccessibile l'intera
applicazione (incluso /health → impatto AWS liveness probe). Test obbligatorio
prima di ogni merge: `curl /health` deve rispondere 200 SENZA credenziali.

---

## Allowed paths

```
src/Middleware/BasicAuthMiddleware.php
src/Application.php
config/app_local.php.example
tests/TestCase/Middleware/BasicAuthMiddlewareTest.php
tests/TestCase/Controller/DashboardControllerTest.php
tests/TestCase/Controller/MetricsControllerTest.php
```

## Forbidden paths

```
config/Migrations/                # NON modificare schema
config/routes.php                 # protezione via middleware, non route
src/Controller/                   # NON toccare controller
coord/                            # solo Planner/Reviewer
```

---

## Dipendenze

```
BLOCKED_BY: TASK_m1_observability
BLOCKS:     TASK_m1_tests_m1

Pre-check:  TASK_m1_observability DONE? NO → BLOCKED
                                        SÌ → pronto per assegnazione

Nota: parallelo con TASK_m1_alert_engine dopo merge di TASK_m1_observability
      (path disgiunti: Middleware/BasicAuthMiddleware vs Service/AlertsService).
      Entrambi toccano Application.php ma in fasi sequenziali distinte:
      — observability: Application.php già mergiato (primo middleware)
      — auth: aggiunge BasicAuthMiddleware in coda (post-routing)
      — alert_engine: NON tocca Application.php → SAFE parallel.
```

---

## DoD

```bash
# GET / senza credenziali → 401
curl -s -w "\nHTTP:%{http_code}" http://localhost:8080/
# → HTTP:401  con header WWW-Authenticate

# GET / con credenziali valide → 200
curl -s -w "\nHTTP:%{http_code}" -u "${APP_AUTH_USER}:${APP_AUTH_PASSWORD}" \
  http://localhost:8080/
# → HTTP:200

# GET /health senza credenziali → 200  (liveness probe esente)
curl -s -w "\nHTTP:%{http_code}" http://localhost:8080/health
# → HTTP:200

# Suite completa PASS
make test
# → OK (N tests, N assertions) — exit 0
```

**Criteri DONE:**
- [ ] `GET /` senza credenziali → 401 + `WWW-Authenticate` header
- [ ] `GET /` con credenziali valide (`APP_AUTH_USER:APP_AUTH_PASSWORD`) → 200
- [ ] `GET /health` senza credenziali → 200 (sempre esente)
- [ ] Credenziali errate → 401 (non 403)
- [ ] `make test` → exit 0
- [ ] `coord/HANDOFF_m1_auth.md` creato con `correlation_id`

---

## Comandi verifica (stack-specifici)

```bash
# Linting
php8.2 -l src/Middleware/BasicAuthMiddleware.php
php8.2 -l src/Application.php

# Test middleware isolato
php8.2 vendor/bin/phpunit tests/TestCase/Middleware/BasicAuthMiddlewareTest.php
# → OK (3 tests, N assertions)

# Verifica critica: /health sempre accessibile
curl -s -w "\nHTTP:%{http_code}" http://localhost:8080/health
# → HTTP:200

# Suite completa
make test
```

---

## Assunzioni

- [A3] M0 aveva accesso libero — M1 introduce auth; test esistenti
       (DashboardControllerTest, MetricsControllerTest) vanno aggiornati
       per passare le credenziali di test tramite env o header
- [A9] PHP 8.2 runtime
- [A13] Le variabili `APP_AUTH_USER` e `APP_AUTH_PASSWORD` sono iniettate
        via ambiente Docker/EC2 — mai hardcoded nel codice
- [A14] `hash_equals()` usato per confronto credenziali (timing-safe)
- [A15] In test environment le credenziali sono impostate via
        `$_ENV` o `putenv()` nel setUp() del TestCase
