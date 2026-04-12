# BOARD — sdi-ops-monitor
<!-- Fonte di verità visiva. In caso di conflitto con STATE.json, STATE.json vince. -->
<!-- Aggiorna dopo ogni HANDOFF DONE. -->
<!-- [UPDATED: 2026-04-02 — Planner pass: aggiunti tabella ruoli completa, routing agenti, regole worktree, conflict detection, protocollo HANDOFF] -->
<!-- [UPDATED: 2026-04-03 — Planner pass M1: Wave 2 sbloccata, 5 task M1 pianificati con dipendenze e parallelism matrix] -->
<!-- [UPDATED: 2026-04-10 — Planner pass M3: Wave 3 DONE, Wave 4 pianificata, 5 task M3, parallelism matrix e sequenza] -->
<!-- [UPDATED: 2026-04-12 — Planner pass M4: Wave 4 → DONE, Wave 5 pianificata, 7 task M4, parallelism matrix, sequenza; W3 INTEGRATION_REPORT smoke_test → PASS] -->

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

## Wave 2 — M1 · Alert Engine, Auth & AWS Integration ✅ DONE

| # | Task | Titolo | Assegnatario | Status | Dipende da | Risk | Agente |
|---|------|--------|-------------|--------|------------|------|--------|
| 1 | TASK_m1_observability | correlation_id + JSON logging | Executor | **DONE** | — | MED | Claude |
| 2 | TASK_m1_alert_engine | Alert threshold engine | Executor | **DONE** | observability | HIGH | Codex |
| 2 | TASK_m1_auth | Basic auth middleware | Executor | **DONE** | observability | HIGH | Codex |
| 3 | TASK_m1_aws_integration | AWS SNS signature validation | Executor | **DONE** | alert_engine | HIGH | Codex |
| 4 | TASK_m1_tests_m1 | PHPUnit M1 integration suite | Executor | **DONE** | alert_engine + auth + aws | LOW | Claude |

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
✅ Soddisfatta — 5/5 DONE + `make test` PASS (17 tests, 48 assertions, exit 0) + SNS signature validation PASS.
Vedere `coord/INTEGRATION_REPORT_wave2.md` per dettaglio. Avvertimenti non bloccanti: W1, W2, W3 → candidate TASK M2.

---

## Wave 3 — M2 · Fix W1/W2/W3 + SQS Scheduler + Log Viewer + Scenario Simulator ✅ DONE

<!-- [UPDATED: 2026-04-07 — Planner pass M2: 6 task pianificati] -->
<!-- [UPDATED: 2026-04-10 — Planner pass M3: Wave 3 marcata DONE, integration_report aggiunto] -->

| # | Task | Titolo | Assegnatario | Status | Dipende da | Risk | Agente |
|---|------|--------|-------------|--------|------------|------|--------|
| 1 | TASK_m2_fix_log_consistency | Fix W1 log format + W3 stale TODO | Executor | **DONE** | — | LOW | Claude |
| 1 | TASK_m2_fix_sns_e2e_test | W2: integration tests SNS pipeline | Executor | **DONE** | — | LOW | Claude |
| 1 | TASK_m2_dashboard_severity | Dashboard semaforo severity-based | Executor | **DONE** | — | LOW | Claude |
| 2 | TASK_m2_sqs_scheduler | AWS SQS polling Command | Executor | **DONE** | — | HIGH | Codex |
| 2 | TASK_m2_log_viewer | Log Viewer web UI | Executor | **DONE** | fix_log_consistency | MED | Codex |
| 3 | TASK_m2_scenario_simulator | SDI/FatturaPA scenario simulator | Executor | **DONE** | sqs_scheduler + log_viewer | MED | Codex |

### Parallelism matrix Wave 3

| Task A | Task B | Parallelo? | Motivo |
|--------|--------|-----------|--------|
| TASK_m2_fix_log_consistency | TASK_m2_fix_sns_e2e_test | **SÌ** | path disgiunti (src/Controller/Api/ vs tests/) — ATTENZIONE: entrambi toccano MetricsController.php — assegnare allo stesso agente in sequenza o gestire merge |
| TASK_m2_fix_log_consistency | TASK_m2_dashboard_severity | **SÌ** | path disgiunti (MetricsController vs DashboardController) |
| TASK_m2_fix_sns_e2e_test | TASK_m2_dashboard_severity | **SÌ** | path disgiunti (tests/Controller/ vs DashboardController) |
| TASK_m2_sqs_scheduler | TASK_m2_log_viewer | **SÌ** | path disgiunti (src/Command/ vs src/Controller/LogViewer/) |
| TASK_m2_log_viewer | TASK_m2_scenario_simulator | NO | entrambi toccano Application.php — seriale obbligatorio |
| TASK_m2_sqs_scheduler | TASK_m2_scenario_simulator | NO | scenario_simulator BLOCKED_BY sqs_scheduler |

### Sequenza di esecuzione Wave 3

```
Step 1 (par):  TASK_m2_fix_log_consistency  ║  TASK_m2_fix_sns_e2e_test  ║  TASK_m2_dashboard_severity
                      ↓ (dopo fix_log_consistency)
Step 2 (par):  TASK_m2_sqs_scheduler  ║  TASK_m2_log_viewer
                      ↓ (dopo sqs_scheduler + log_viewer)
Step 3 (seq):  TASK_m2_scenario_simulator
```

### Exit condition Wave 3
✅ Soddisfatta — 6/6 DONE + `make test` PASS (30 tests, 87 assertions, exit 0) + SQS dry-run PASS + scenario simulator PASS.
Vedere `coord/INTEGRATION_REPORT_wave3.md` per dettaglio. Osservazioni non bloccanti: OBS-1…OBS-4 → candidate TASK M3.

---

## Wave 4 — M3 · Demo Ready ✅ DONE

<!-- [UPDATED: 2026-04-10 — Planner pass M3: Wave 4 pianificata, 5 task] -->
<!-- [UPDATED: 2026-04-12 — Planner pass M4: Wave 4 marcata DONE, Wave 5 pianificata; W3 smoke_test_after_merge → PASS] -->

| # | Task | Titolo | Assegnatario | Status | Dipende da | Risk | Agente |
|---|------|--------|-------------|--------|------------|------|--------|
| 1 | TASK_m3_ai_diagnostics | OpenRouter AI diagnostics + fallback | Executor | **DONE** | — | MED | Codex / Qwen |
| 1 | TASK_m3_ci_pipeline | GitHub Actions CI pipeline | Executor | **DONE** | — | LOW | Claude |
| 1 | TASK_m3_runbook | Runbook operativo | Executor | **DONE** | — | LOW | Claude |
| 2 | TASK_m3_fix_wave3_obs | Fix OBS-1…OBS-4 da Integration Report W3 | Executor | **DONE** | ai_diagnostics | LOW | Claude |
| 2 | TASK_m3_phpcs | PHPCS CakePHP standard + phpcbf 105 fixes + CI step | Executor | **DONE** | ci_pipeline | LOW | Claude |

### Parallelism matrix Wave 4

| Task A | Task B | Parallelo? | Motivo |
|--------|--------|-----------|--------|
| TASK_m3_ai_diagnostics | TASK_m3_ci_pipeline | **SÌ** | path disgiunti (src/ vs .github/) |
| TASK_m3_ai_diagnostics | TASK_m3_runbook | **SÌ** | path disgiunti (src/ vs docs/) |
| TASK_m3_ci_pipeline | TASK_m3_runbook | **SÌ** | path disgiunti (.github/ vs docs/) |
| TASK_m3_ai_diagnostics | TASK_m3_fix_wave3_obs | NO | entrambi toccano Application.php — fix_wave3_obs BLOCKED_BY ai_diagnostics |
| TASK_m3_ci_pipeline | TASK_m3_phpcs | NO | entrambi toccano ci.yml — phpcs BLOCKED_BY ci_pipeline |
| TASK_m3_fix_wave3_obs | TASK_m3_phpcs | **SÌ** | path disgiunti (src/ vs phpcs.xml/Makefile) — ma entrambi sono step 2 |

### Sequenza di esecuzione Wave 4

```
Step 1 (par):  TASK_m3_ai_diagnostics  ║  TASK_m3_ci_pipeline  ║  TASK_m3_runbook
                      ↓ (dopo ai_diagnostics)    ↓ (dopo ci_pipeline)
Step 2 (par):  TASK_m3_fix_wave3_obs        ║  TASK_m3_phpcs
```

### Exit condition Wave 4
✅ Soddisfatta — 5/5 DONE + `make test` PASS (35/35, 106 assertions) + CI green + PHPCS configurato (baseline 265 errors, continue-on-error:true) + `docs/RUNBOOK.md` presente (692 righe).
Vedere `coord/INTEGRATION_REPORT_wave4.md` per dettaglio. Avvertimenti non bloccanti: W1, W2, W3 (risolti in M4).

---

## Wave 5 — M4 · AWS Real Deploy 🚀 IN PROGRESS

<!-- [UPDATED: 2026-04-12 — Planner pass M4: Wave 5 pianificata, 7 task, parallelism matrix, sequenza] -->

| # | Task | Titolo | Assegnatario | Status | Dipende da | Risk | Agente |
|---|------|--------|-------------|--------|------------|------|--------|
| 1 | TASK_m4_eb_infra | EB environment + PHP 8.2 + .ebextensions scaffold | Executor | **TODO** | — | MED | Codex / Qwen |
| 2 | TASK_m4_env_vars | EB environment variables (secrets management) | Executor | BLOCKED | eb_infra | HIGH | Codex / umano |
| 2 | TASK_m4_cloudwatch | CloudWatch logs + metric filters + alarms | Executor | BLOCKED | eb_infra | MED | Codex / Qwen |
| 2 | TASK_m4_govway_mtls | GovWay/mTLS SSL + proxy headers config | Executor | BLOCKED | eb_infra | HIGH | Codex / umano |
| 3 | TASK_m4_rds_config | RDS MySQL 8.0 + CakePHP connection + migration | Executor | BLOCKED | env_vars | MED | Codex / Qwen |
| 4 | TASK_m4_sqs_worker | Real SQS worker (cron via .ebextensions) | Executor | BLOCKED | rds_config | HIGH | Codex / umano |
| 4 | TASK_m4_healthcheck_aws | Health check verification on AWS EB | Executor | BLOCKED | rds_config | LOW | Claude |

### Parallelism matrix Wave 5

| Task A | Task B | Parallelo? | Motivo |
|--------|--------|-----------|--------|
| TASK_m4_eb_infra | qualsiasi altro | NO | deve essere primo — crea l'ambiente EB |
| TASK_m4_env_vars | TASK_m4_cloudwatch | **SÌ** | path disgiunti (config/app.php.example vs .ebextensions/05) |
| TASK_m4_env_vars | TASK_m4_govway_mtls | ⚠️ ATTENZIONE | entrambi toccano config/app.php — assegnare allo stesso agente in sequenza |
| TASK_m4_cloudwatch | TASK_m4_govway_mtls | ⚠️ ATTENZIONE | govway_mtls tocca config/app.php; cloudwatch solo .ebextensions/05 — parallelo possibile con merge attento |
| TASK_m4_rds_config | TASK_m4_cloudwatch | **SÌ** | path disgiunti — cloudwatch non dipende da rds_config |
| TASK_m4_rds_config | TASK_m4_govway_mtls | ⚠️ ATTENZIONE | entrambi toccano config/app.php — seriale obbligatorio o merge attento |
| TASK_m4_sqs_worker | TASK_m4_healthcheck_aws | **SÌ** | path disgiunti (src/Command/ vs .ebextensions/09) — entrambi step 4 |

### Sequenza di esecuzione Wave 5

```
Step 1 (seq):  TASK_m4_eb_infra
                        ↓
Step 2 (par):  TASK_m4_env_vars  ║  TASK_m4_cloudwatch
               TASK_m4_govway_mtls  (seriale dopo env_vars — config/app.php overlap)
                        ↓ (dopo env_vars)
Step 3 (seq):  TASK_m4_rds_config
                        ↓
Step 4 (par):  TASK_m4_sqs_worker  ║  TASK_m4_healthcheck_aws
```

Sequenza raccomandata considerando gli overlap su `config/app.php`:
```
Step 1:  TASK_m4_eb_infra
Step 2a: TASK_m4_env_vars         (tocca config/app.php.example + .ebextensions/03)
Step 2b: TASK_m4_cloudwatch        (parallelo con env_vars — path disgiunti)
Step 2c: TASK_m4_govway_mtls       (dopo env_vars — config/app.php)
Step 3:  TASK_m4_rds_config        (dopo env_vars — config/app.php)
Step 4a: TASK_m4_sqs_worker        (dopo rds_config)
Step 4b: TASK_m4_healthcheck_aws   (dopo rds_config — parallelo con sqs_worker)
```

### Exit condition Wave 5
Tutti i 7 task DONE + `eb status` Health:Ok + `GET https://<eb-url>/health` → 200 + RDS connesso + SQS worker attivo (eb logs) + CloudWatch alarms configurati + `docs/govway_mtls.md` presente + `make test` PASS (35/35, nessuna regressione locale).

---

## Halt attivi

_Nessuno._

---

## Replan history

_Nessuno._
