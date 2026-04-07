# TASK_m2_log_viewer — Log Viewer web UI per JSON logs

<!-- [CREATED: 2026-04-07 — Planner pass M2: feature log viewer M2] -->

---

## Metadata

```
created:   2026-04-07T00:00:00Z
updated:   2026-04-07T00:00:00Z
assignee:  Executor
status:    BLOCKED
wave:      3
milestone: M2
risk_tier: MED
```

---

## Obiettivo

Implementare una pagina web protetta da BasicAuth (`GET /logs`) che mostra le ultime N
righe dei log applicativi in formato tabellare, parse-ando ogni riga come JSON.
Permette agli operatori SRE di ispezionare i log strutturati senza accesso SSH al server.

---

## Scope

- [x] `src/Controller/LogViewerController.php` — nuovo controller:
      - Classe: `App\Controller\LogViewerController extends AppController`
      - `index()` — legge le ultime N righe (default: 200, configurabile via `?lines=N`)
        da `logs/app.log` (path relativo alla root CakePHP: `ROOT . DS . 'logs' . DS . 'app.log'`)
      - Ogni riga viene parsata con `json_decode()`:
        - Riga JSON valida → array con campi `timestamp`, `level`, `message`, `correlation_id`, `context`
        - Riga non JSON → mostrata come stringa raw con livello 'raw'
      - Filtro opzionale via query string:
        - `?level=error` → filtra per livello (info/warning/error/debug)
        - `?correlation_id=<uuid>` → filtra per correlation_id esatto
      - Se `logs/app.log` non esiste: mostra messaggio "No log file found" senza errore 500
      - PHPDoc completo

- [x] `templates/LogViewer/index.php` — nuova view:
      - Layout Bootstrap (usa `templates/layout/default.php` esistente)
      - Header: "Log Viewer" + form filtri (level selector, correlation_id input, lines input)
      - Tabella log entries: colonne `Timestamp`, `Level` (badge colorato), `Message`,
        `Correlation ID`, `Context` (JSON compact)
      - Badge colori per livello: error=bg-danger, warning=bg-warning, info=bg-info, debug=bg-secondary
      - Paginazione semplice: "Showing last N lines" — no DB query, solo file slicing
      - Link rapidi: "Last 50", "Last 200", "Last 500"

- [x] `src/Application.php` — aggiungere route per `/logs`:
      ```php
      $builder->connect('/logs', ['controller' => 'LogViewer', 'action' => 'index']);
      ```
      La route è protetta da BasicAuthMiddleware automaticamente (già in middleware stack).

- [x] `tests/TestCase/Controller/LogViewerControllerTest.php` — nuovo:
      - `testIndexReturns200WithValidCredentials` — GET `/logs` con BasicAuth → 200
      - `testIndexReturns401WithoutCredentials` — GET `/logs` senza auth → 401
      - `testIndexHandlesMissingLogFile` — nessun `logs/app.log` → 200, messaggio "No log file"
        (usa log path fittizio o temp dir)

## Non-scope

- [ ] NON implementare streaming real-time (WebSocket/SSE) — solo snapshot statico
- [ ] NON implementare ricerca full-text nei log
- [ ] NON persistere log in DB — sempre file-based
- [ ] NON esporre log di sistema fuori da `logs/app.log`
- [ ] NON implementare download/export log (task futuro)
- [ ] NON modificare il formato di logging (definito da W1 — deve essere già fatto)

---

## Risk tier

**MED** — legge file dal filesystem del server; potenziale information disclosure se
il path non è correttamente delimitato. Mitigazione: usare `ROOT . DS . 'logs'`
hardcoded, non accettare path dall'utente. La route è protetta da BasicAuth.

---

## Allowed paths

```
src/Controller/LogViewerController.php
templates/LogViewer/index.php
tests/TestCase/Controller/LogViewerControllerTest.php
src/Application.php
```

## Forbidden paths

```
src/Service/
src/Model/
config/Migrations/
coord/
```

---

## Dipendenze

```
BLOCKED_BY: TASK_m2_fix_log_consistency
            (il Log Viewer deve poter parsare log con formato json_encode consistente
             — W1 deve essere DONE prima di implementare il viewer)

BLOCKS:     TASK_m2_scenario_simulator
            (scenario_simulator tocca Application.php per route — serializzare
             per evitare merge conflict su Application.php)

Pre-check:  TASK_m2_fix_log_consistency DONE? NO → BLOCKED
                                                SÌ → pronto per assegnazione

Parallelismo:
  Parallelo con TASK_m2_sqs_scheduler dopo Step 1 (fix tasks).
  Path disgiunti: src/Command/ vs src/Controller/LogViewerController.php.
  ATTENZIONE: entrambi potrebbero toccare Application.php se sqs_scheduler
  aggiunge route (ma il command CLI non ha route) → nessun overlap reale.
```

---

## DoD

```bash
# Lint
php8.2 -l src/Controller/LogViewerController.php
php8.2 -l templates/LogViewer/index.php

# Test controller
php8.2 vendor/bin/phpunit tests/TestCase/Controller/LogViewerControllerTest.php --testdox
# → OK (3 tests) — exit 0

# Suite completa
make test
# → OK (≥ 20 tests) — exit 0

# Verifica manuale (opzionale in dev)
curl -u testuser:testpassword http://localhost:8080/logs
# → 200 HTML con tabella log
```

**Criteri DONE:**
- [ ] `GET /logs` con BasicAuth → 200 HTML con tabella log entries
- [ ] `GET /logs` senza auth → 401
- [ ] Log file assente → 200 "No log file found" (no 500)
- [ ] Filtro `?level=error` funzionante
- [ ] Filtro `?correlation_id=<uuid>` funzionante
- [ ] Badge colori per livello log presenti nella view
- [ ] `LogViewerControllerTest` → 3 test PASS
- [ ] `make test` → exit 0
- [ ] `coord/HANDOFF_m2_log_viewer.md` creato con `correlation_id`

---

## Comandi verifica

```bash
php8.2 -l src/Controller/LogViewerController.php
php8.2 vendor/bin/phpunit tests/TestCase/Controller/LogViewerControllerTest.php --testdox
make test
```

---

## Assunzioni

- [A1] I log applicativi sono scritti in `logs/app.log` (path CakePHP standard: `ROOT/logs/app.log`).
       Se CakePHP è configurato con un path diverso, l'Executor adatta il path di lettura.
- [A2] Il Log Viewer legge solo `logs/app.log` — nessun log di debug o error separato
       (CakePHP 5 default: tutti i livelli vanno in `app.log` con JsonFormatter).
- [A3] La route `/logs` è aggiunta in `src/Application.php` nella sezione `routes()`
       (metodo `routes()` in `Application.php` — verificare la struttura CakePHP 5 esistente).
- [A4] Il filtro `?lines=N` accetta max 1000 per evitare out-of-memory su file grandi —
       documentare il limite nel HANDOFF.
- [A5] Information security: il path del log file è hardcoded nel controller (non accettato
       dall'utente) — questo è un requisito di sicurezza, non opzionale.
