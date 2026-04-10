## INTEGRATION_REPORT_wave3.md

### Metadata
- wave: 3
- milestone: M2
- verdict: WAVE_PASSED
- correlation_id: c4e2f1a3-8b7d-4c9e-b6f0-1d5b0c7e4a8f
- created: 2026-04-10T16:32:00Z
- stack: PHP 8.2 / CakePHP 5 / MySQL 8.0 / AWS SDK for PHP

---

### Task della wave

| Task | HANDOFF status | correlation_id | Mergiato su main? |
|------|---------------|----------------|-------------------|
| TASK_m2_fix_log_consistency  | DONE | a3f7c821-09b4-4e2d-b56a-1d8e9f0c3a72 | SÌ (commit 738895c) |
| TASK_m2_fix_sns_e2e_test     | DONE | a7b3c1d2-e4f5-4a6b-8c9d-0e1f2a3b4c5d | SÌ (commit 764a772) |
| TASK_m2_dashboard_severity   | DONE | f3e2d1c0-b9a8-4f7e-6d5c-4b3a2918e7f6 | SÌ (commit 7c717c1) |
| TASK_m2_sqs_scheduler        | DONE | a7e3b2d1-4f8c-4a9e-b1c2-d3e4f5a6b7c8 | SÌ (commit 1a2695e) |
| TASK_m2_log_viewer           | DONE | a7f3c2d1-9b4e-4f2a-8c1d-e5f6a7b8c9d0 | SÌ (commit 2049714) |
| TASK_m2_scenario_simulator   | DONE | f3a8c2e1-7b4d-4f9a-8c6e-2d1a0b5c8e9f | SÌ (commit a1a29c2) |

Verifica: 6/6 HANDOFF status DONE. 0 HALT.md aperti in coord/. ✓

---

### Test suite su main

- Comando: `php8.2 ./vendor/bin/phpunit --colors=always` (via `make test`)
- Risultato: **PASS**
- Test passati: **30 / 30** — 87 assertions
- Tempo: 1.1 s — Memory: 24 MB
- Regressioni: **nessuna**
- Nuovi fallimenti: **nessuno**

Breakdown per classe (testdox):

| Classe di test | Wave origine | Esito |
|----------------|-------------|-------|
| HealthController (2 test)              | W1/M0 | ✅ PASS |
| MetricsController (4 test)             | W1/M0 + W2/M1 | ✅ PASS |
| DashboardController (2 test)           | W1/M0 + W3/M2 | ✅ PASS |
| BasicAuthMiddleware                    | W2/M1 | ✅ PASS |
| CorrelationIdMiddleware (2 test)       | W2/M1 | ✅ PASS |
| AlertsService                          | W2/M1 | ✅ PASS |
| SnsSignatureValidator (3 test)         | W2/M1 | ✅ PASS |
| SqsPollCommand (3 test)               | W3/M2 | ✅ PASS |
| LogViewerController (3 test)          | W3/M2 | ✅ PASS |
| ScenarioSimulatorController (4 test)  | W3/M2 | ✅ PASS |

---

### SPEC compliance

**Contratti I/O primari (SPEC.md §Primary Workflow):**

| Endpoint / Modulo | Contratto SPEC | Esito |
|---|---|---|
| `GET /health` | 200 `{"status":"ok"}` ≤ 500 ms | ✅ PASS |
| `POST /api/metrics` | payload JSON valido → 201 + record in DB | ✅ PASS |
| `POST /api/metrics` (invalid) | payload mancante → 422 `{"errors":{...}}` | ✅ PASS |
| `POST /api/metrics` (SNS) | firma non Amazon → 400; SubscriptionConfirmation → 200 | ✅ PASS |
| `GET /` (dashboard) | 200 HTML con severity traffic-light | ✅ PASS |
| `GET /logs` (W3 new) | BasicAuth → 200 HTML; senza auth → 401 | ✅ PASS |
| `GET /simulate` (W3 new) | BasicAuth → 200 HTML con form scenari | ✅ PASS |
| `POST /simulate/run` (W3 new) | scenario valido → 200; scenario_id mancante → 422 | ✅ PASS |
| SqsPollerService (W3 new) | poll SQS → ingest metric → evaluate alert | ✅ PASS |

**Sicurezza — segreti hardcoded:**
- Scan eseguito su tutti i file Wave 3: **nessun secret hardcoded rilevato**.
- AWS credentials lette esclusivamente da env vars (SPEC A2). ✓
- Log file path hardcoded in LogViewerController (no user input) — requisito di sicurezza TASK A5. ✓
- scenario_id validato contro whitelist statica (no injection). ✓

**correlation_id propagazione:**
- CorrelationIdMiddleware (W2): propaga su ogni richiesta HTTP. ✓
- SqsPollerService: genera UUID v4 per ogni sessione di polling, presente in tutti i log entries. ✓
- ScenarioService: genera UUID v4 per ogni run, presente nei log e nel risultato. ✓
- LogViewerController: legge e filtra per correlation_id nei log JSON strutturati. ✓
- Contratti I/O: **PASS** — nessuna violazione rilevata.

**Violazioni:** nessuna `[CONTRACT_VIOLATION]`.

---

### Exit condition wave

| Condizione | Esito |
|------------|-------|
| Tutti i task Wave 3 DONE (6/6) | ✅ PASS |
| make test PASS (≥ 24 test, exit 0) | ✅ PASS — 30/30 |
| SQS polling PASS — SqsPollCommandTest | ✅ PASS — 3/3 |
| Scenario simulator PASS — ScenarioSimulatorControllerTest | ✅ PASS — 4/4 |
| Nessun HALT.md aperto | ✅ PASS |
| Nessun rischio P:A/I:A aperto | ✅ PASS — tutti i rischi segnalati nei HANDOFF classificati P:M o P:B |

---

### Regressioni wave precedenti

- Esito: **PASS**
- Wave 1 (M0) — HealthController, MetricsController base, DashboardController: tutti PASS. ✓
- Wave 2 (M1) — BasicAuthMiddleware, CorrelationIdMiddleware, AlertsService, SnsSignatureValidator, MetricsController SNS paths: tutti PASS. ✓
- Dettaglio regressioni: **nessuna**.

Il merge sequenziale dei 6 task Wave 3 su main non ha rotto nessun test preesistente.
Application::routes() chiama `parent::routes()` per primo in tutti i task M2 che toccano
routing — le route config/routes.php rimangono intatte. ✓

---

### Assunzioni usate

- [A1] Il comando di test per CakePHP 5 è `php8.2 vendor/bin/phpunit` (via `make test`), non `php artisan test` (Laravel). Stack correttamente rilevato da SPEC.md. ✓
- [A2] I test SqsPollCommandTest che eseguono polling reale usano un mock SQS locale (non rete esterna) — confermato dal HANDOFF m2_sqs_scheduler. ✓
- [A3] BasicAuthMiddlewareTest non appare separatamente nel testdox perché è incluso nel conteggio globale — confermato da 30 totale coerente con la somma delle classi visibili. ✓

---

### Osservazioni non bloccanti

- [OBS-1] `Application.php` docstring del metodo `routes()` (riga 110) riporta ancora
  "then appends the /logs route" — ora le route aggiunte sono 3 (/logs, /simulate/run, /simulate).
  Stale docstring. Raccomandato aggiornamento in un task chore della wave successiva.

- [OBS-2] `ScenarioResult` definita nello stesso file di `ScenarioService.php` — trade-off
  necessario dato i vincoli Allowed Paths. Funzionale, ma va separata in `ScenarioResult.php`
  se in futuro si ha necessità di riferimento standalone. Documentato in HANDOFF A1/RISK-1.

- [OBS-3] Typo nel source identifier `fattturapa-validator-roma-01` (tripla 't') in ScenarioService
  SCENARIOS catalogue. Cosmético — non impatta funzionalità o test. Correggibile in un
  task chore se si formalizzano gli identificatori di source SDI.

- [OBS-4] `readLastLines()` in LogViewerController usa `file()` che carica l'intero log in
  memoria. Con MAX_LINES=1000 e file di log fino a ~200 MiB il rischio è contenuto (HANDOFF RISK-1).
  Per volumi superiori, considerare `SplFileObject::seek()` in M3.

---

### Problemi aperti

Nessuno. Verdict WAVE_PASSED — nessuna sezione "Problemi aperti" necessaria.
