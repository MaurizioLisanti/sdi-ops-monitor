## INTEGRATION_REPORT_wave2.md

### Metadata
- wave: 2
- milestone: M1
- verdict: WAVE_PASSED
- correlation_id: 2ce38385-bd4d-4f61-8f42-753cba9ae02d
- created: 2026-04-06T00:03:00Z
- stack: PHP 8.2 / CakePHP 5 / MySQL 8.0 / AWS

---

### Task della wave

| Task | HANDOFF status | Mergiato su main? |
|------|----------------|-------------------|
| TASK_m1_observability   | DONE | SÌ — commit `7a57653` |
| TASK_m1_alert_engine    | DONE | SÌ — commit `3a640c1` |
| TASK_m1_auth            | DONE | SÌ — commit `66f25a2` |
| TASK_m1_aws_integration | DONE | SÌ — commit `c5df882` |
| TASK_m1_tests_m1        | DONE | SÌ — commit `a843041` |

HALT aperti: **nessuno**.

---

### Test suite su main

- Comando: `php8.2 vendor/bin/phpunit --testdox`
- Risultato: **PASS**
- Test passati: **17 / 17**
- Deprecazioni: **0**
- Regressioni: **nessuna**
- Nuovi fallimenti: **nessuno**

```
Alerts Service
 ✔ Evaluate creates alert when threshold exceeded
 ✔ Evaluate skips alert below threshold
 ✔ Evaluate returns null for unknown metric

Basic Auth Middleware
 ✔ Dashboard returns 401 without credentials
 ✔ Dashboard returns 200 with valid credentials
 ✔ Health is exempt from auth
 ✔ Wrong credentials return 401

Correlation Id Middleware
 ✔ Header propagated
 ✔ Header generated if absent

Dashboard Controller
 ✔ Index returns 200

Health Controller
 ✔ Health returns 200
 ✔ Health returns 503 when db down

Metrics Controller
 ✔ Add returns 201
 ✔ Add returns 422 on invalid payload

Sns Signature Validator
 ✔ Valid signature returns true
 ✔ Invalid signature returns false
 ✔ Rejects cert from non amazon domain

OK (17 tests, 48 assertions) — exit 0
```

---

### SPEC compliance

- Contratti I/O: **PASS**
- correlation_id propagato: **SÌ**
- Violazioni: **nessuna**

#### Dettaglio contratti verificati

| Endpoint / Modulo | Contratto SPEC | Esito |
|---|---|---|
| `POST /api/metrics` (payload valido, no SNS header) | → 201 `{"id": <int>}` | PASS |
| `POST /api/metrics` (payload invalido) | → 422 `{"errors": {...}}` | PASS |
| `POST /api/metrics` (SNS Notification firma invalida) | → 400 `{"error": "..."}` — nessun dato scritto | PASS |
| `POST /api/metrics` (SNS Notification firma valida) | → pipeline normale → 201 | PASS |
| `POST /api/metrics` (SNS SubscriptionConfirmation) | → 200 `{"status": "ok"}` — nessuna Metric creata | PASS |
| `POST /api/metrics` (SigningCertURL non-Amazon) | → 400 immediato, nessun HTTP fetch | PASS |
| `GET /health` (DB up) | → 200 `{"status":"ok"}` | PASS |
| `GET /health` (DB down) | → 503 `{"status":"error","detail":"..."}` | PASS |
| `GET /health` senza credenziali | → 200 (exempt da BasicAuth) | PASS |
| `GET /` senza credenziali | → 401 + `WWW-Authenticate: Basic` | PASS |
| `GET /` con credenziali valide | → 200 HTML con "SDI Ops Monitor" | PASS |

#### Propagazione correlation_id

```
CorrelationIdMiddleware
  → legge X-Correlation-ID (o genera UUID v4 se assente)
  → imposta request attribute 'correlation_id'
  → echo nel response header X-Correlation-ID
  ↓
MetricsController::handleSnsRequest()
  → legge request->getAttribute('correlation_id')
  → passa a Log::info/warning per ogni log entry SNS
  ↓
MetricsController::ingestMetric()
  → passa correlationId a AlertsService::evaluate()
  ↓
AlertsService::evaluate()
  → include correlation_id in ogni log entry (info, warning, error)
```

Catena completa: HTTP → Middleware → Controller → Service → Log. ✓

#### Ordine middleware stack (Application.php)

```
CorrelationIdMiddleware        ← primo: correlation_id su tutte le request, incluse 401
ErrorHandlerMiddleware
AssetMiddleware
RoutingMiddleware
BasicAuthMiddleware            ← dopo routing: exempt /health funziona correttamente
BodyParserMiddleware
CsrfProtectionMiddleware
```

Ordine corretto: CorrelationId prima di BasicAuth garantisce che i log di 401 abbiano correlation_id. ✓

---

### Exit condition wave

| Condizione | Esito |
|---|---|
| Tutti i task DONE (5/5) | **PASS** |
| `make test` PASS su main | **PASS** (17/17, exit 0) |
| SNS signature validation PASS | **PASS** (3/3 test, fail-closed, domain allowlist) |
| Nessun HALT aperto | **PASS** |
| Nessuna regressione M0 | **PASS** (Health 2/2, Dashboard 1/1, Metrics 2/2) |

---

### Regressioni wave precedenti

- Esito: **PASS**
- Test wave 1 (M0) su main:

```
php8.2 vendor/bin/phpunit \
  tests/TestCase/Controller/HealthControllerTest.php \
  tests/TestCase/Controller/DashboardControllerTest.php \
  tests/TestCase/Controller/MetricsControllerTest.php
→ OK (5 tests, 19 assertions) — exit 0
```

Nessuna dipendenza rotta tra moduli M0 e M1.
`GET /health` ancora exempt da BasicAuth ✓.
`POST /api/metrics` backward-compatible (path normale invariato quando header SNS assente) ✓.

---

### Assunzioni usate

- [A1] `config/app_local.php` non presente nel repo (gestito dall'operatore, cfr. A4 in STATE.json).
  Il `JsonFormatter` è documentato in `config/app_local.php.example` ed è responsabilità
  dell'operatore copiarlo e configurarlo prima del deploy.
- [A2] Il test database `sdi_ops_monitor_test` era raggiungibile durante l'esecuzione
  della suite (`make test` PASS con test che persistono Alert e Metric in DB).
- [A3] No integration test E2E per il path SNS completo (HTTP POST → MetricsController →
  SnsSignatureValidator → ingestMetric). Copertura attuale: unit test di SnsSignatureValidator
  isolato. Il path controller-level è coperto solo da review del diff.

---

### Problemi aperti / avvertimenti (non bloccanti)

**[W1] — Log format parzialmente inconsistente** (P2)
- Modulo: `MetricsController`, `AlertsService`
- Descrizione: I log `info`/`warning` usano `Log::info('message', ['key' => 'val'])` (context array
  CakePHP), mentre i log `error` in `ingestMetric()` usano `json_encode([...])` esplicito.
  Con `JsonFormatter` configurato in `app_local.php`, entrambi producono output JSON, ma
  il pattern non è uniforme tra i livelli di log.
- Impatto: nessuno a runtime se JsonFormatter è attivo; rischio se il logger è cambiato.
- Azione suggerita: standardizzare su `Log::info(json_encode([...]))` in M2, oppure
  accettare il pattern CakePHP context come sufficiente (JsonFormatter lo serializza).

**[W2] — Nessun integration test E2E per pipeline SNS** (P2)
- Modulo: `MetricsController` + `SnsSignatureValidator`
- Descrizione: Il path `POST /api/metrics` con header `X-Amz-Sns-Message-Type` non ha un
  test di integrazione HTTP. La copertura si basa su unit test di `SnsSignatureValidator`
  + revisione del diff del controller.
- Azione suggerita: aggiungere in M2 un test `MetricsControllerTest::testSnsNotificationInvalidSignatureReturns400()`
  con stub del validator via override o fixture.

**[W3] — TODO pre-esistente in AppController** (P2, informativo)
- File: `src/Controller/AppController.php:12`
- Descrizione: `TODO (Planner): add auth component, rate-limit middleware, correlation_id injection.`
  Questo TODO è dello scaffold M0 e non è stato rimosso. Non impatta il comportamento
  (auth e correlation_id sono implementati nei middleware, non nel controller).
- Azione suggerita: rimuovere o aggiornare il TODO nel Planner pass di M2.

---

### Verdict finale

```
verdict:        WAVE_PASSED
wave:           2
milestone:      M1
test su main:   PASS (17/17 — exit 0 — 0 deprecazioni)
contratti I/O:  rispettati
regressioni:    nessuna (M0 5/5 ancora verde)
exit condition: tutte soddisfatte

avvertimenti non bloccanti: W1 (log inconsistency), W2 (no E2E SNS test), W3 (stale TODO)

action Planner: Wave 2 — M1 COMPLETATA
  → autorizzato avvio Wave 3 / M2
  → passa questo Integration Report al Planner Agent v3
     come campo "Output Wave precedente" del prossimo Context Slice
  → considera W1, W2, W3 come candidate TASK_fix in M2
```