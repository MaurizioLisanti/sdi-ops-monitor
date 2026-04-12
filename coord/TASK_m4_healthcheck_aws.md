# TASK_m4_healthcheck_aws — Health Check Verification on AWS EB

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

Configurare il path del health check EB su `/health`, verificare che `GET /health`
risponda correttamente su AWS con DB raggiungibile, e documentare la procedura
di verifica operativa. Exit: EB console mostra health "Ok"; `/health` → 200 su HTTPS.

---

## Scope

- [ ] `.ebextensions/09_health.config` → `HealthCheckPath: /health` (per load balancer o EB health agent)
- [ ] Verifica `GET https://<eb-url>/health` → 200 `{"status":"ok"}` (con RDS connesso)
- [ ] Verifica `EB console` → environment health → "Ok" (verde)
- [ ] `docs/healthcheck_aws.md` → guida: come interpretare health EB, come forzare health check,
      cosa fare se degraded, soglie allarme
- [ ] Opzionale: CloudWatch alarm su EB environment health degraded
      (se non già coperto da TASK_m4_cloudwatch `sdi-ops-EBHealth`)

## Non-scope

- NON modifica `HealthController.php` (già implementato in M0 — funziona)
- NON aggiunge nuovi endpoint
- NON modifica `tests/` (nessun nuovo test — verifica è infrastrutturale)
- NON tocca RDS, SQS o CloudWatch direttamente (solo verifica)

---

## Risk tier

**LOW** — task di verifica e configurazione path; nessuna modifica al codice applicativo.

---

## Allowed paths

```
.ebextensions/09_health.config
docs/healthcheck_aws.md
```

## Forbidden paths

```
src/Controller/HealthController.php   (già implementato — non toccare)
tests/
config/app.php
```

---

## Dipendenze

```
BLOCKED_BY:  TASK_m4_rds_config   (DB deve essere connesso per health check completo)
BLOCKS:      N/A
Pre-check:   TASK_m4_rds_config status == DONE? → SÌ → pronto / NO → stato BLOCKED
Parallelo con: TASK_m4_sqs_worker (path disgiunti — entrambi step 4)
```

---

## DoD

```
[ ] .ebextensions/09_health.config presente con HealthCheckPath: /health
[ ] curl https://<eb-url>/health → 200 {"status":"ok"} (documentato nel HANDOFF con output reale)
[ ] EB console → environment health → "Ok" (screenshot o eb status output nel HANDOFF)
[ ] docs/healthcheck_aws.md presente (≥ 3 sezioni: verifica health, interpretazione stato, troubleshooting)
[ ] make test PASS (35/35 — nessuna regressione locale)
[ ] HANDOFF_m4_healthcheck_aws.md creato con correlation_id (UUID v4)
[ ] STATE.json aggiornato: status → DONE, last_updated → <ISO8601>
```

## Comandi verifica

```bash
# Verifica health check via HTTPS (sostituire <eb-url>)
curl -s https://<eb-url>/health

# Verifica stato EB (include health)
eb status

# Verifica HealthCheckPath configurato
aws elasticbeanstalk describe-environments \
  --environment-names sdi-ops-monitor-prod \
  --query "Environments[0].{Health:Health,HealthStatus:HealthStatus}"

# Test suite locale
make test
```

---

## Assunzioni

- [A_M4_20] `HealthController` risponde 200 `{"status":"ok"}` quando DB è raggiungibile
            e 503 `{"status":"error"}` quando DB non disponibile — comportamento da M0
- [A_M4_21] Su EB single-instance senza ALB, l'health check EB agent usa il path configurato
            per determinare lo stato dell'ambiente
