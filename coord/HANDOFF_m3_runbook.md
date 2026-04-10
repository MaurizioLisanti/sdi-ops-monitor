## HANDOFF_m3_runbook.md

### Metadata
- task: TASK_m3_runbook
- status: DONE
- correlation_id: e7c2d4f1-8b3a-4e9c-a5b6-7d8e9f0a1b2c
- run_id: run-20260410-004
- created: 2026-04-10T18:15:00Z
- branch: main
- agent: claude-sonnet-4-6

### Summary
Created `docs/RUNBOOK.md` (692 lines): bilingual EN/IT operations guide covering all 12
sections from Quick Start to Escalation Path, with 17 copy-paste curl examples, defined
SLO targets, SDI/FatturaPA-specific troubleshooting (error codes 003/004/009), escalation
matrix, and Emergency Stop procedure including coord/HALT.md template.

### Files changed
- `docs/RUNBOOK.md` — added (692 lines, 12 sections, bilingual EN/IT)
- `coord/STATE.json` — modified (TASK_m3_runbook status → DONE, M3 percent_done → 80, tasks_done → 4)
- `coord/HANDOFF_m3_runbook.md` — added

### Commands run
```
wc -l docs/RUNBOOK.md                     → 692 lines (≥ 100 required) PASS
grep 10 required sections                 → 10/10 OK (Quick Start, Dashboard, Metrics,
                                             Log Viewer, Scenario, SQS, AI Diagnostics,
                                             Health Check, Troubleshooting, Emergency)
grep -c "curl" docs/RUNBOOK.md            → 17 (≥ 5 required) PASS
```

### Assunzioni fatte
- [A1] `docs/` directory created alongside the file — confirmed by mkdir and Write tool.
- [A2] Runbook language: bilingual English (primary) and Italian (section subtitles, key terms)
       per user override; TASK specifies English only but user explicitly requested bilingual.
- [A3] Alert thresholds documented in §4 and §8 align with AlertsService::DEFAULT_THRESHOLDS
       (cpu ≥ 80/95, memory ≥ 85/95) — sourced from existing service code, not invented.
- [A4] Escalation contact placeholders (Slack channels, email addresses) use generic patterns
       — replace with real contact details before production deployment.
- [A5] SDI error codes 003/004/009 are the same codes already documented in ScenarioService
       scenario descriptions — consistent with existing codebase context.
- [A6] `make test` not run for this task — RUNBOOK is pure documentation with no PHP code.
       No risk of regression; the task spec confirms "only documentation — no runtime impact".

### Rischi / TODO residui
- [R1] §12 Escalation Path contains placeholder contacts (Slack `#ops-alerts`, email pattern).
       Replace with real team contacts before first on-call rotation.
- [R2] SLO targets in §9 (p95 latency < 300 ms for POST /api/metrics) are based on SPEC.md
       design targets, not measured baselines. Validate against real traffic in M4+.
- [R3] `TASK_m3_phpcs` remains the only incomplete M3 task. BLOCKED_BY TASK_m3_ci_pipeline
       which is now DONE — phpcs can be started immediately.
- [R4] Runbook does not cover AWS ECS/EC2 deployment (explicitly out of scope for M3 per
       TASK Non-scope). Add a §13 Deployment section in M4 when deploy target is confirmed.
