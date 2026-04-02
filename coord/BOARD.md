# BOARD — sdi-ops-monitor
<!-- Fonte di verità visiva. In caso di conflitto con STATE.json, STATE.json vince. -->
<!-- Aggiorna dopo ogni HANDOFF DONE. -->

---

## Wave 1 — M0 · Scaffold & Core Endpoints

| Task | Titolo | Assegnatario | Status | Dipende da | Smoke test |
|------|--------|-------------|--------|------------|------------|
| TASK_scaffold_m0_boot | CakePHP 5 boot + migrazioni | Planner/Executor | TODO | — | PENDING |
| TASK_m0_health_endpoint | GET /health probe | Planner/Executor | TODO | scaffold_m0_boot | PENDING |
| TASK_m0_metric_ingestion | POST /api/metrics | Planner/Executor | TODO | scaffold_m0_boot | PENDING |
| TASK_m0_dashboard | GET / dashboard | Planner/Executor | TODO | scaffold_m0_boot | PENDING |
| TASK_m0_tests_smoke | PHPUnit smoke suite | Planner/Executor | TODO | health + ingestion + dashboard | PENDING |

### Exit condition Wave 1
Tutti i 5 task DONE + `make test` PASS + nessun rischio P:A/I:A aperto.

---

## Wave 2 — M1 · Alert Engine & AWS Integration

| Task | Titolo | Assegnatario | Status | Dipende da | Smoke test |
|------|--------|-------------|--------|------------|------------|
| TASK_m1_alert_engine | Alert threshold engine | Planner/Executor | BLOCKED | Wave 1 DONE | PENDING |
| TASK_m1_aws_integration | AWS SNS/SQS ingestion | Planner/Executor | BLOCKED | Wave 1 DONE | PENDING |
| TASK_m1_observability | correlation_id + structured logs | Planner/Executor | BLOCKED | Wave 1 DONE | PENDING |

### Exit condition Wave 2
Tutti i 3 task DONE + `make test` PASS + SNS signature validation PASS.

---

## Halt attivi

_Nessuno._

---

## Replan history

_Nessuno._
