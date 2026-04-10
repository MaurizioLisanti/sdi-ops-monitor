---
task_id: TASK_m3_runbook
created: 2026-04-10T18:00:00Z
updated: 2026-04-10T18:00:00Z
milestone: M3
assignee: Executor
suggested_agent: Claude
status: TODO
risk_tier: LOW
correlation_id_template: "UUID-v4 generato al momento della creazione HANDOFF"
---

# TASK_m3_runbook — Runbook Operativo

## Obiettivo

Produrre `docs/RUNBOOK.md`: guida operativa per SRE/Ops che copre avvio,
health check, gestione alert, log viewing, simulazione scenari, SQS polling,
AI diagnostics e procedure di emergenza. Sufficiente per un nuovo operatore
che si avvicina al sistema per la prima volta — in demo o in on-call.

---

## Scope

- [ ] Sezione **Quick Start**: `make install`, `make migrate`, avvio server locale,
      verifica `GET /health` → `{"status":"ok"}`
- [ ] Sezione **Dashboard & Alert**: `GET /`, semantica del semaforo severity
      (green/yellow/red), come fare acknowledge su un alert
- [ ] Sezione **Metrics Ingestion**: `POST /api/metrics` — esempi curl copiabili
      con payload realistico e risposta attesa 201
- [ ] Sezione **Log Viewer**: `GET /logs`, filtrare per `correlation_id`,
      interpretare i campi dei log JSON strutturati
- [ ] Sezione **Scenario Simulator**: `GET /simulate`, elenco scenari disponibili,
      differenza dry-run vs live, esempio di output
- [ ] Sezione **SQS Polling**: `bin/cake sqs_poll` — variabili env richieste,
      dry-run mode, troubleshooting connessione SQS
- [ ] Sezione **AI Diagnostics**: `GET /ai-diagnostics` — setup `OPENROUTER_API_KEY`,
      comportamento fallback deterministico, come leggere la diagnosis card
- [ ] Sezione **Health Check & SLO**: target latency p95, error rate accettabile,
      come leggere `/health` e quando paginare
- [ ] Sezione **Troubleshooting**: top 5 problemi comuni con causa + soluzione
- [ ] Sezione **Emergency Stop**: quando creare `coord/HALT.md`,
      procedura di replan con il Complexity Manager

## Non-scope

- [ ] NON toccare codice PHP
- [ ] NON modificare file di configurazione
- [ ] NON documentare deploy AWS/ECS (out of scope M3)
- [ ] NON includere architettura interna dettagliata (appartiene a SPEC.md)

---

## Risk tier: LOW

- Solo documentazione — nessun impatto sul runtime applicativo
- Può essere eseguito in parallelo a tutti gli altri task M3

---

## Allowed paths

```
docs/RUNBOOK.md
docs/
```

## Forbidden paths

```
src/
tests/
config/
templates/
.github/
composer.json
Makefile
```

---

## Dipendenze

- **BLOCKED_BY**: N/A
- **BLOCKS**: N/A
- **Pre-check**: tutti BLOCKED_BY DONE? → **SÌ** → stato: **TODO** (pronto)
- **Nota**: parallelo a TASK_m3_ai_diagnostics e TASK_m3_ci_pipeline;
  la sezione AI Diagnostics va scritta descrivendo l'interfaccia progettata
  (può precedere l'implementazione — il contratto è già definito nel TASK)

---

## DoD

- [ ] `docs/RUNBOOK.md` creato, leggibile, ≥ 100 righe
- [ ] Almeno 10 sezioni operative coperte (Quick Start … Emergency Stop)
- [ ] Ogni sezione ha almeno 1 esempio di comando copiabile (curl / make / bin/cake)
- [ ] Sezione AI Diagnostics descrive sia modalità API che fallback deterministico
- [ ] Linguaggio: inglese (AGENTS.md CODE STANDARDS)
- [ ] `coord/HANDOFF_m3_runbook.md` creato con `correlation_id` UUID v4
- [ ] diff summary nel HANDOFF

---

## Comandi verifica

```bash
# Verifica file creato e non vuoto
wc -l docs/RUNBOOK.md
# → atteso ≥ 100 righe

# Verifica sezioni obbligatorie
for section in "Quick Start" "Dashboard" "Metrics" "Log Viewer" \
  "Scenario" "SQS" "AI Diagnostics" "Health Check" \
  "Troubleshooting" "Emergency"; do
  grep -q "$section" docs/RUNBOOK.md \
    && echo "OK: $section" \
    || echo "MISSING: $section"
done

# Verifica presenza esempi curl
grep -c "curl" docs/RUNBOOK.md
# → atteso ≥ 5
```

---

## Assunzioni

- [A1] La cartella `docs/` non esiste ancora — l'Executor la crea insieme al file
- [A2] Lingua inglese obbligatoria per tutto il documento (AGENTS.md CODE STANDARDS)
- [A3] La sezione AI Diagnostics può essere scritta prima che TASK_m3_ai_diagnostics
        sia DONE — usa il contratto dell'interfaccia definito nel TASK file
- [A4] Il runbook referenzia i comandi Makefile esistenti (`make install`, `make test`,
        `make migrate`) — non inventa comandi non presenti nel Makefile
