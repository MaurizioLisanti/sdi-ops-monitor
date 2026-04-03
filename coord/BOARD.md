# BOARD — sdi-ops-monitor
<!-- Fonte di verità visiva. In caso di conflitto con STATE.json, STATE.json vince. -->
<!-- Aggiorna dopo ogni HANDOFF DONE. -->
<!-- [UPDATED: 2026-04-02 — Planner pass: aggiunti tabella ruoli completa, routing agenti, regole worktree, conflict detection, protocollo HANDOFF] -->
<!-- [UPDATED: 2026-04-03 — Planner pass M1: Wave 2 sbloccata, 5 task M1 pianificati con dipendenze e parallelism matrix] -->

---

## Tabella ruoli

| Ruolo | Responsabilità | Allowed paths | Risk tier | Tooling |
|-------|---------------|---------------|-----------|---------|
| **Planner** | Compilare skeleton governance, definire TASK completi prima che l'Executor venga attivato | `coord/`, `AGENTS.md`, `SPEC.md` | LOW–MED | claude-code |
| **Executor** | Implementare un task alla volta nei path consentiti dal TASK corrente | `src/`, `config/`, `templates/`, `tests/` | MED–HIGH | Codex / Qwen Coder |
| **Reviewer** | Code review, verifica DoD, aggiorna STATE.json post-merge | lettura tutto, scrittura `coord/` | LOW | claude-code |
| **Complexity Manager** | Rilevare blocchi, riordinare wave, gestire replan | lettura tutto, scrittura `coord/` | MED | claude-code |

---

## Routing agenti

```
HIGH  (security / auth / PII / cost explosion / audit)  →  Codex o revisione umana
MED   (feature / wiring / DB write / integrazioni)      →  Codex o Qwen Coder
LOW   (docs / test boilerplate / refactor / template)   →  Claude
```

---

## Regole worktree

```bash
# Avvio task
git worktree add ../sdi-ops-monitor-<task_id> -b task/<task_id>
# → lavora nel worktree isolato

# Verifica prima del merge
cd ../sdi-ops-monitor-<task_id>
make test   # deve essere PASS

# Merge e pulizia
cd /workspaces/sdi-ops-monitor
git merge task/<task_id>
git worktree remove ../sdi-ops-monitor-<task_id>
git branch -d task/<task_id>
```

**Regole:**
1. `main` deve essere sempre verde — non mergiare se `make test` FAIL.
2. Un worktree attivo per task — non aprire task successivo finché il corrente non è mergiato.
3. Worktree rimosso dopo merge (pulizia obbligatoria).

---

## Protocollo HANDOFF

Vedi HANDOFF Schema completo in `AGENTS.md` (sezione "HANDOFF Schema") — schema condiviso,
non duplicato qui per evitare drift.

Checklist pre-consegna HANDOFF:
- [ ] `coord/HANDOFF_<task_id>.md` creato
- [ ] `correlation_id` (UUID v4) presente nel HANDOFF
- [ ] `make test` PASS documentato nel HANDOFF (output ultime 10 righe)
- [ ] `STATE.json` aggiornato: `status → DONE`, `last_updated → <ISO8601>`
- [ ] Branch mergiato su `main`, worktree rimosso

---

## Conflict detection — allowed paths overlap check

Prima di assegnare due task in parallelo, verificare che gli allowed paths siano disgiunti.

**Wave 1 — check pre-assegnazione:**

| Task A | Task B | Overlap? | Sicuro per parallelo? |
|--------|--------|----------|----------------------|
| scaffold_m0_boot | health_endpoint | NO | — (health dopo scaffold) |
| health_endpoint | metric_ingestion | NO | SÌ |
| health_endpoint | dashboard | NO | SÌ |
| metric_ingestion | dashboard | NO | SÌ |
| tests_smoke | qualsiasi altro | NO (test/ disgiunti) | NO — dipendenza logica |

**Regola:** se overlap → assegnazione seriale obbligatoria, indipendentemente dalla
disponibilità dell'agente.

---

## Wave 1 — M0 · Scaffold & Core Endpoints ✅ DONE

| Task | Titolo | Assegnatario | Status | Dipende da | Risk | Smoke test |
|------|--------|-------------|--------|------------|------|------------|
| TASK_scaffold_m0_boot | CakePHP 5 boot + migrazioni | Executor | **DONE** | — | MED | PENDING |
| TASK_m0_health_endpoint | GET /health probe | Executor | **DONE** | scaffold_m0_boot | LOW | PENDING |
| TASK_m0_metric_ingestion | POST /api/metrics | Executor | **DONE** | scaffold_m0_boot | MED | PENDING |
| TASK_m0_dashboard | GET / dashboard | Executor | **DONE** | scaffold_m0_boot | LOW | PENDING |
| TASK_m0_tests_smoke | PHPUnit smoke suite + Makefile fix | Executor | **DONE** | health + ingestion + dashboard | LOW | PENDING |

### Exit condition Wave 1
✅ Soddisfatta — 5/5 DONE + `make test` PASS (5 tests, 19 assertions, exit 0).

---

## Wave 2 — M1 · Alert Engine, Auth & AWS Integration 🚀 IN PROGRESS

| # | Task | Titolo | Assegnatario | Status | Dipende da | Risk | Agente |
|---|------|--------|-------------|--------|------------|------|--------|
| 1 | TASK_m1_observability | correlation_id + JSON logging | Executor | **TODO** | — | MED | Claude |
| 2 | TASK_m1_alert_engine | Alert threshold engine | Executor | BLOCKED | observability | HIGH | Codex |
| 2 | TASK_m1_auth | Basic auth middleware | Executor | BLOCKED | observability | HIGH | Codex |
| 3 | TASK_m1_aws_integration | AWS SNS signature validation | Executor | BLOCKED | alert_engine | HIGH | Codex |
| 4 | TASK_m1_tests_m1 | PHPUnit M1 integration suite | Executor | BLOCKED | alert_engine + auth + aws | LOW | Claude |

### Parallelism matrix Wave 2

| Task A | Task B | Parallelo? | Motivo |
|--------|--------|-----------|--------|
| TASK_m1_observability | qualsiasi altro | NO | deve essere primo (Application.php) |
| TASK_m1_alert_engine | TASK_m1_auth | **SÌ** | path disgiunti (Service/ vs Middleware/) |
| TASK_m1_aws_integration | TASK_m1_auth | NO | aws dopo alert_engine (MetricsController) |
| TASK_m1_tests_m1 | qualsiasi altro | NO | dipende da tutti e tre |

### Sequenza di esecuzione Wave 2

```
Step 1 (seq):  TASK_m1_observability
                      ↓
Step 2 (par):  TASK_m1_alert_engine  ║  TASK_m1_auth
                      ↓ (dopo alert_engine)
Step 3 (seq):  TASK_m1_aws_integration
                      ↓ (dopo tutti)
Step 4 (seq):  TASK_m1_tests_m1
```

### Exit condition Wave 2
Tutti i 5 task DONE + `make test` PASS + SNS signature validation PASS.

---

## Halt attivi

_Nessuno._

---

## Replan history

_Nessuno._
