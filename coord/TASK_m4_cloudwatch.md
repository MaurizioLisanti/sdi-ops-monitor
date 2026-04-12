# TASK_m4_cloudwatch — CloudWatch Logs, Metric Filters & Alarms

## Metadata

```
created:   2026-04-12T00:00:00Z
updated:   2026-04-12T00:00:00Z
assignee:  Executor
status:    BLOCKED
milestone: M4
wave:      5
```

---

## Obiettivo

Configurare CloudWatch per l'ambiente EB: log group per i log applicativi CakePHP,
metric filter per error rate, e almeno 2 allarmi operativi (error rate, EB health).
Exit: log visibili in CloudWatch console; allarmi configurati con soglie operative.

---

## Scope

- [ ] `.ebextensions/05_cloudwatch.config` → CloudWatch agent: stream `logs/error.log` e `logs/debug.log`
      verso log group `/aws/elasticbeanstalk/sdi-ops-monitor/app`
- [ ] Metric filter: `ERROR` count per minuto da log group (pattern: `"level":"error"`)
- [ ] Alarm 1: `sdi-ops-ErrorRate` — trigger se > 10 errori/minuto per 2 periodi consecutivi
- [ ] Alarm 2: `sdi-ops-EBHealth` — trigger se EB environment health != Ok per 5 minuti
- [ ] SNS topic `sdi-ops-alerts` → email operatore (subscription via console — non nel codice)
- [ ] `docs/cloudwatch.md` → guida: dove trovare log, come interpretare allarmi, come silenziarli

## Non-scope

- NON crea dashboard CloudWatch (fuori scope M4)
- NON modifica il codice di logging applicativo (già JSON strutturato da M1)
- NON configura RDS metrics alarms (operazione console AWS — fuori scope)
- NON tocca `src/`, `tests/`, `config/app.php`

---

## Risk tier

**MED** — configurazione infrastruttura AWS; nessun impatto sul codice applicativo.

---

## Allowed paths

```
.ebextensions/05_cloudwatch.config
docs/cloudwatch.md
```

## Forbidden paths

```
src/          (nessuna modifica applicativa)
tests/
config/app.php
```

---

## Dipendenze

```
BLOCKED_BY:  TASK_m4_eb_infra    (ambiente EB deve esistere per configurare CloudWatch)
BLOCKS:      N/A
Pre-check:   TASK_m4_eb_infra status == DONE? → SÌ → pronto / NO → stato BLOCKED
Parallelo con: TASK_m4_env_vars, TASK_m4_govway_mtls (path disgiunti)
```

---

## DoD

```
[ ] .ebextensions/05_cloudwatch.config presente e valido (eb deploy senza errori)
[ ] Log group /aws/elasticbeanstalk/sdi-ops-monitor/app visibile in CloudWatch console
[ ] Log stream con righe di log CakePHP presenti
[ ] Metric filter ERROR configurato sul log group
[ ] Alarm sdi-ops-ErrorRate presente (stato OK o INSUFFICIENT_DATA al deploy)
[ ] Alarm sdi-ops-EBHealth presente
[ ] SNS topic sdi-ops-alerts creato (subscription email operatore — fuori codice)
[ ] docs/cloudwatch.md presente con guida operativa
[ ] make test PASS (35/35 — nessuna regressione locale)
[ ] HANDOFF_m4_cloudwatch.md creato con correlation_id (UUID v4)
[ ] STATE.json aggiornato: status → DONE, last_updated → <ISO8601>
```

## Comandi verifica

```bash
# Verifica log group presente
aws logs describe-log-groups \
  --log-group-name-prefix "/aws/elasticbeanstalk/sdi-ops-monitor" \
  --query "logGroups[*].logGroupName"

# Verifica metric filters
aws logs describe-metric-filters \
  --log-group-name "/aws/elasticbeanstalk/sdi-ops-monitor/app" \
  --query "metricFilters[*].filterName"

# Verifica allarmi
aws cloudwatch describe-alarms \
  --alarm-name-prefix "sdi-ops-" \
  --query "MetricAlarms[*].{Name:AlarmName,State:StateValue}"

# Test suite locale
make test
```

---

## Assunzioni

- [A_M4_1] Account AWS con permessi CloudWatch Logs, CloudWatch Alarms, SNS
- [A_M4_11] I log CakePHP sono scritti in `logs/error.log` e `logs/debug.log` — path standard CakePHP 5
- [A_M4_12] CloudWatch agent disponibile su Amazon Linux 2023 (preinstallato su EB PHP platform)
- [A_M4_13] Costo CloudWatch free tier: 5GB log ingest/mese, 10 allarmi, 3 dashboard — sufficiente per M4
