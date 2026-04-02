# AGENTS.md — sdi-ops-monitor
<!-- Governance multi-agente. Leggi prima di ogni intervento. -->
<!-- [UPDATED: 2026-04-02 — Planner pass: aggiunti Workflow Git, DoD universale, Stop condition, Logging, Error model, Emergency stop, Risk routing] -->

---

## Complexity score

```
Stack rilevato : PHP 8.2 / CakePHP 5 / MySQL 8.0 / AWS
PKG            : sdi_ops_monitor
Complexity     : MED  [task:2pt · dipendenze:2pt · moduli:1pt · risk:1pt = 6pt]
Next step      : Complexity Manager consigliato per replan tra wave
```

---

## Ruoli agente

| Agente | Responsabilità | Allowed paths | Risk tier | Tooling |
|--------|---------------|---------------|-----------|---------|
| **Planner** | Compilare skeleton, definire step nei TASK, mantenere coerenza governance | `coord/`, `AGENTS.md`, `SPEC.md` | LOW–MED | claude-code |
| **Executor** | Implementare i task assegnati, un task alla volta, nei path consentiti | `src/`, `config/`, `templates/`, `tests/` | MED–HIGH | Codex / Qwen Coder |
| **Reviewer** | Code review, verifica DoD, aggiornamento STATE.json | lettura tutto, scrittura `coord/` | LOW | claude-code |
| **Complexity Manager** | Rilevare blocchi, riordinare wave, gestire replan | lettura tutto, scrittura `coord/` | MED | claude-code |

---

## Workflow Git

```
Regola: 1 task = 1 branch = 1 worktree isolato

Ciclo per ogni task:
  1. git worktree add ../sdi-ops-monitor-<task_id> -b task/<task_id>
  2. Eseguire il task nel worktree isolato
  3. make test  →  PASS obbligatorio prima del merge
  4. PR → review → merge su main
  5. git worktree remove ../sdi-ops-monitor-<task_id>
  6. Aggiornare STATE.json: status → DONE, smoke_test_after_merge → PASS

Regola di pulizia: main deve essere sempre verde (make test PASS).
Non aprire task successivo finché main non è verde.
```

---

## DoD universale (valido per ogni task)

Ogni task è DONE solo quando **tutti** i seguenti criteri sono soddisfatti:

```
1. make test PASS (exit code 0)
2. coord/HANDOFF_<task_id>.md creato con:
   - correlation_id (UUID v4 generato dall'agente)
   - diff summary (file modificati + descrizione breve)
   - stato: DONE
3. STATE.json aggiornato:
   - status: DONE
   - last_updated: <ISO8601>
   - smoke_test_after_merge: PASS (dopo merge su main)
4. Nessun file fuori dagli Allowed paths modificato
```

---

## Stop condition

```
Trigger IMMEDIATO di STOP:
  - File modificato fuori dagli Allowed paths del task corrente
    → Crea coord/HANDOFF_<task_id>.md con status: NEEDS_REVIEW
    → STOP — non procedere al passo successivo
    → Notifica Reviewer con percorso del file non autorizzato

  - Syntax error PHP rilevato da php -l
    → STOP — non fare commit
    → Correggere prima di procedere

  - make test FAIL dopo implementazione
    → STOP — non aprire task successivo
    → Impostare status: NEEDS_REVIEW in STATE.json
```

---

## Routing risk-tier

```
HIGH  (security / auth / PII / policy / audit / cost)  →  Codex o revisione umana
MED   (feature / wiring / integrazioni / DB write)     →  Codex o Qwen Coder
LOW   (docs / test boilerplate / refactor / template)  →  Claude
```

Task correnti per tier:
- HIGH: TASK_m1_alert_engine, TASK_m1_aws_integration
- MED:  TASK_scaffold_m0_boot, TASK_m0_metric_ingestion, TASK_m1_observability
- LOW:  TASK_m0_health_endpoint, TASK_m0_dashboard, TASK_m0_tests_smoke

---

## Logging

```
Formato: JSON strutturato obbligatorio
Campi obbligatori per ogni log entry:
  {
    "timestamp": "<ISO8601>",
    "level": "debug|info|warning|error|critical",
    "correlation_id": "<UUID v4>",
    "run_id": "<UUID v4 per sessione agente>",
    "task_id": "<TASK_xxx>",
    "message": "<testo>",
    "context": {}
  }

In M0: logging minimale (error/critical obbligatorio).
In M1: correlation_id propagato da HTTP request header X-Correlation-ID.
Mai loggare valori di credenziali, token o secret.
```

---

## Error model

```
Gerarchia errori applicazione:
  AppException  (base — tutti gli errori applicativi)
    ├── ValidationException   (422 — input non valido)
    ├── DatabaseException     (503 — DB non raggiungibile)
    └── IntegrationException  (502 — servizi esterni AWS)

Regole:
  - Never fail silently: ogni eccezione deve essere loggata
  - HTTP 5xx → log level ERROR con stack trace
  - HTTP 4xx → log level WARNING (no stack trace in produzione)
  - Eccezioni non gestite → log level CRITICAL → alert immediato (M1)
```

---

## Emergency stop

```
Trigger che richiedono HALT immediato:

  PII_LEAK    : Dato personale identificabile nel log o in risposta API
                → Creare coord/HALT_<timestamp>.md
                → STOP qualsiasi attività
                → Escalare a revisione umana

  SECRET_LEAK : Credenziale / token / password nel codice o log
                → Creare coord/HALT_<timestamp>.md
                → Revocare il secret PRIMA di ogni altra azione
                → STOP

  COST_EXPLODE: Costo AWS stimato > budget definito (vedere SPEC.md)
                → Creare coord/HALT_<timestamp>.md
                → STOP polling / job schedulati
                → Escalare a Engineering Manager

Template coord/HALT_<timestamp>.md:
  # HALT — <tipo>
  **Data**: <ISO8601>
  **Trigger**: <PII_LEAK | SECRET_LEAK | COST_EXPLODE>
  **Rilevato da**: <agente>
  **Dettaglio**: <descrizione senza includere il dato sensibile>
  **Azione immediata**: <cosa è stato fermato>
  **Escalation**: <chi contattare>
```

---

## Regole operative (obbligatorie)

1. **Un task alla volta** — non avviare il task successivo finché il corrente non è DONE.
2. **Smoke test post-merge** — obbligatorio dopo ogni merge su `main` (vedi sezione dedicata).
3. **STATE.json è la fonte di verità** — aggiornalo dopo ogni HANDOFF DONE.
4. **No business logic nel seed** — i file skeleton non devono contenere logica reale.
5. **Non rinominare/spostare file** salvo task dedicato approvato.
6. **Vietato TBD/FIXME/placeholder generici** — usa `TODO (Planner): <testo specifico>`.
7. **correlation_id obbligatorio** in ogni HANDOFF e in ogni log entry ERROR/CRITICAL.

---

## Smoke test post-merge (obbligatorio dopo ogni task)

Eseguito dall'utente dopo ogni merge su `main`.

```bash
make test
```

- **SE PASS**: aggiorna `STATE.json` → campo `smoke_test_after_merge: "PASS"` per il task → procedi al task successivo.
- **SE FAIL**: imposta `status: "NEEDS_REVIEW"` per il task in STATE.json — **non avviare il task successivo**.

---

## HANDOFF Schema

Ogni agente produce un file `coord/HANDOFF_<task_id>.md` al completamento del task.

Struttura obbligatoria (schema condiviso — non modificare):

```markdown
# HANDOFF — <TASK_ID>

**Da**: <agente>
**A**: <agente successivo>
**Data**: <ISO8601>
**agent**: <nome agente usato — es. claude-code>
**correlation_id**: <UUID v4>

## Stato
[DONE | NEEDS_REVIEW | BLOCKED]

## Cosa è stato fatto
- <file modificato>: <descrizione breve modifica>
- ...

## Diff summary
File modificati: N
Linee aggiunte: +X  Linee rimosse: -Y
Branch: task/<task_id>

## Test run
Comando: make test
Risultato: [PASS | FAIL]
Output: <ultimi 10 righe rilevanti>

## Rischi aperti
- [Pxx/Ixx] <descrizione> → Mitigazione: <azione>

## Next step
<nome task successivo> — <agente consigliato>
```

---

## Wave summary

| Wave | Milestone | Tasks | Entry condition |
|------|-----------|-------|-----------------|
| 1 | M0 | scaffold_boot, health_endpoint, metric_ingestion, dashboard, tests_smoke | — |
| 2 | M1 | alert_engine, aws_integration, observability | Wave 1 DONE |

---

## Parallelism matrix (Wave 1)

```
TASK_scaffold_m0_boot          [seq — primo, nessuna dipendenza]
         │
    ┌────┼────────────┐
    ▼    ▼            ▼
health  metric_ingestion  dashboard
    │        │               │
    └────────┴───────────────┘
                  │
                  ▼
         TASK_m0_tests_smoke
```

| Task A | Task B | Parallelo? | Motivo |
|--------|--------|-----------|--------|
| health_endpoint | metric_ingestion | SÌ | path disgiunti |
| health_endpoint | dashboard | SÌ | path disgiunti |
| metric_ingestion | dashboard | SÌ | path disgiunti |
| tests_smoke | qualsiasi Wave 1 | NO | dipende da tutti e tre |

`health`, `metric_ingestion` e `dashboard` possono partire in parallelo dopo `scaffold_boot`.
`tests_smoke` deve attendere tutti e tre.

---

## Quick reference

```bash
make install      # composer install
make up           # php -S :8080
make migrate      # cake migrations migrate
make test         # phpunit
make routes       # lista route registrate
make clean        # svuota cache CakePHP
```

Fonte di verità pipeline: `coord/STATE.json`
Backlog visivo: `coord/BOARD.md`
Specifiche: `coord/SPEC.md`
