# AGENTS.md — sdi-ops-monitor
<!-- Governance multi-agente. Leggi prima di ogni intervento. -->

---

## Complexity score

```
Stack rilevato : PHP 8.2 / CakePHP 5 / MySQL 8.0 / AWS
PKG            : sdi_ops_monitor
Complexity     : MED  [task:1pt · dipendenze:1pt · moduli:1pt · risk:1pt = 4pt]
Next step      : Complexity Manager consigliato
```

---

## Ruoli agente

| Agente | Responsabilità | Accesso |
|--------|---------------|---------|
| **Planner** | Compilare i TODO nei file skeleton, definire step dettagliati nei TASK | Lettura repo, scrittura coord/ |
| **Executor** | Implementare i task assegnati, un task alla volta | Scrittura src/, config/, templates/, tests/ |
| **Reviewer** | Code review, verifica DoD, aggiornamento STATE.json | Lettura tutto, scrittura coord/ |
| **Complexity Manager** | Rilevare blocchi, riordinare wave, gestire replan | Lettura tutto, scrittura coord/ |

---

## Regole operative (obbligatorie)

1. **Un task alla volta** — non avviare il task successivo finché il corrente non è DONE.
2. **Smoke test post-merge** — obbligatorio dopo ogni merge su `main` (vedi sezione dedicata).
3. **STATE.json è la fonte di verità** — aggiornalo dopo ogni HANDOFF DONE.
4. **No business logic nel seed** — i file skeleton non devono contenere logica reale.
5. **Non rinominare/spostare file** salvo task dedicato approvato.
6. **Vietato TBD/FIXME/placeholder generici** — usa `TODO (Planner): <testo specifico>`.

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

Struttura obbligatoria:

```markdown
# HANDOFF — <TASK_ID>

**Da**: <agente>
**A**: <agente successivo>
**Data**: <ISO8601>
**agent**: <nome agente usato — es. claude-code>

## Stato
[DONE | NEEDS_REVIEW | BLOCKED]

## Cosa è stato fatto
- ...

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
| 1 | M0 | scaffold_boot, health, metric_ingestion, dashboard, tests_smoke | — |
| 2 | M1 | alert_engine, aws_integration, observability | Wave 1 DONE |

---

## Parallelism matrix (Wave 1)

```
TASK_scaffold_m0_boot          [seq — primo]
         │
    ┌────┴──────────┐
    ▼               ▼
TASK_m0_health  TASK_m0_metric_ingestion
    │               │
    └────────┬───────┘
             ▼
      TASK_m0_dashboard
             │
             ▼
      TASK_m0_tests_smoke
```

`health` e `metric_ingestion` possono partire in parallelo dopo `scaffold_boot`.  
`dashboard` può partire dopo `scaffold_boot` (non dipende da health o ingestion logicamente).  
`tests_smoke` deve attendere i tre task precedenti.

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
