---
task_id: TASK_m3_ai_diagnostics
created: 2026-04-10T18:00:00Z
updated: 2026-04-10T18:00:00Z
milestone: M3
assignee: Executor
suggested_agent: Codex o Qwen Coder
status: TODO
risk_tier: MED
correlation_id_template: "UUID-v4 generato al momento della creazione HANDOFF"
---

# TASK_m3_ai_diagnostics — OpenRouter AI Diagnostics + Deterministic Fallback

## Obiettivo

Aggiungere endpoint `GET /ai-diagnostics` (protetto da BasicAuth) che raccoglie
le ultime metriche e alert dal DB, li invia a un LLM via OpenRouter API e
restituisce una diagnosi in linguaggio naturale. Se `OPENROUTER_API_KEY` non è
configurata o l'API risponde con errore, il sistema usa un motore deterministico
(regole basate su soglie) per produrre la diagnosi senza dipendenze esterne.

---

## Scope

- [ ] `AiDiagnosticsService`: raccoglie ultime 20 metriche + alert aperti, costruisce
      prompt, chiama OpenRouter API con timeout 5 s
- [ ] Fallback deterministico: se API non disponibile → analisi rule-based
      (es. cpu_usage > 80% → CRITICAL, error_rate > 5% → WARNING)
- [ ] `AiDiagnosticsController`: `GET /ai-diagnostics` → HTML con diagnosis card
      (BasicAuth richiesta — riusa middleware esistente)
- [ ] Template `templates/Pages/ai_diagnostics.php`: Bootstrap 5, mostra diagnosis +
      source badge (AI / fallback) + timestamp
- [ ] `AiDiagnosticsServiceTest`: mock HTTP client, assert fallback attivo senza API key,
      assert AI path con mock 200
- [ ] `AiDiagnosticsControllerTest`: assert 200 con auth, assert 401 senza auth
- [ ] Route aggiunta in `src/Application.php` (coerenza con routing M2)

## Non-scope

- [ ] NON implementare streaming/WebSocket responses
- [ ] NON salvare diagnosis in DB (solo in-memory per request)
- [ ] NON usare Guzzle — usare CakePHP `Http\Client` o `stream_context` nativo PHP
- [ ] NON toccare altri controller o tabelle esistenti
- [ ] NON modificare il sistema di auth (riusare BasicAuth middleware M1)
- [ ] NON aggiungere caching della diagnosis

---

## Risk tier: MED

- API key gestita via env var — nessun secret hardcoded (SPEC A2)
- Fallback deterministico garantisce funzionamento offline (demo-safe)
- HTTP call a OpenRouter in-process, timeout 5 s — nessun job asincrono
- Nessuna scrittura DB in questo task

---

## Allowed paths

```
src/Service/AiDiagnosticsService.php
src/Controller/AiDiagnosticsController.php
templates/Pages/ai_diagnostics.php
tests/TestCase/Controller/AiDiagnosticsControllerTest.php
tests/TestCase/Service/AiDiagnosticsServiceTest.php
src/Application.php
```

## Forbidden paths

```
src/Middleware/
src/Controller/MetricsController.php
src/Controller/LogViewerController.php
src/Controller/ScenarioSimulatorController.php
src/Service/ScenarioService.php
config/routes.php
```

---

## Dipendenze

- **BLOCKED_BY**: N/A
- **BLOCKS**: TASK_m3_fix_wave3_obs
  (entrambi toccano `src/Application.php` — fix_wave3_obs deve andare dopo
   che ai_diagnostics ha aggiunto la propria route)
- **Pre-check**: tutti BLOCKED_BY DONE? → **SÌ** → stato: **TODO** (pronto)

---

## DoD

- [ ] `make test` PASS — nessuna regressione sui 30 test esistenti
- [ ] Almeno 2 nuovi test: `AiDiagnosticsControllerTest` (auth + 200) +
      `AiDiagnosticsServiceTest` (fallback deterministico + mock AI path)
- [ ] `GET /ai-diagnostics` con auth → 200 HTML con diagnosis visibile
- [ ] `GET /ai-diagnostics` senza auth → 401
- [ ] Fallback deterministico attivo quando `OPENROUTER_API_KEY` non impostata
- [ ] Nessun secret hardcoded — OPENROUTER_API_KEY solo via env
- [ ] `coord/HANDOFF_m3_ai_diagnostics.md` creato con `correlation_id` UUID v4
- [ ] diff summary nel HANDOFF
- [ ] Tutti i commenti in inglese (AGENTS.md CODE STANDARDS)
- [ ] PHPDoc su ogni metodo pubblico (AGENTS.md CODE STANDARDS)

---

## Comandi verifica

```bash
# Test suite — nessuna regressione, nuovi test inclusi
make test

# Smoke manuale (se DB disponibile)
curl -u admin:secret http://localhost:8080/ai-diagnostics
# → 200 HTML con "AI Diagnostics" e diagnosis card

# Auth check
curl -v http://localhost:8080/ai-diagnostics
# → 401 Unauthorized

# Fallback deterministico (senza API key)
# → unset OPENROUTER_API_KEY; curl -u admin:secret http://localhost:8080/ai-diagnostics
# → badge "Deterministic Fallback" visibile in pagina
```

---

## Assunzioni

- [A1] `OPENROUTER_API_KEY` assente → fallback deterministico attivo
        (nessun errore esposto all'utente — solo badge "Fallback" in UI)
- [A2] Endpoint OpenRouter: `https://openrouter.ai/api/v1/chat/completions`
        con header `Authorization: Bearer <key>`
- [A3] Model default: `mistralai/mistral-7b-instruct` (free tier) —
        override via env `OPENROUTER_MODEL`
- [A4] I test usano mock dell'HTTP client — nessuna chiamata reale a OpenRouter in CI
- [A5] BasicAuth credentials esistenti da M1 (`BASIC_AUTH_USER`, `BASIC_AUTH_PASS`) —
        riusare senza creare nuove variabili env
