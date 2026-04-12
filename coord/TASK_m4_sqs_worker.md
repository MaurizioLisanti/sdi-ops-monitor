# TASK_m4_sqs_worker — Real SQS Queue Worker on AWS

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

Collegare `SqsPollCommand` (già implementato in M2) alla coda SQS AWS reale, configurare
il processo worker su EB tramite cron job `.ebextensions`, verificare il flusso
end-to-end: messaggio SQS → polling → record persistito in RDS MySQL.
Exit: messaggio inviato su coda reale → worker lo consuma → record in DB.

---

## Scope

- [ ] Verifica `SqsPollCommand` legge `SQS_QUEUE_URL` e `AWS_DEFAULT_REGION` da env vars
      (già presente in M2 — adatta se necessario, no refactor)
- [ ] `.ebextensions/08_sqs.config` → cron job ogni minuto: `php bin/cake sqs_poll --dry-run=false`
      Nota: no EB worker tier (richiede SQS separata con `AWSEBWorkerCronLeader` — fuori scope);
      usare cron via `.ebextensions` su single-instance
- [ ] Verifica coda SQS reale raggiungibile (IAM Role o AWS credentials da env)
- [ ] Test end-to-end documentato in HANDOFF: send 1 msg → verifica record in DB
- [ ] `docs/sqs_worker.md` → guida: creare coda SQS, configurare permessi IAM, monitorare DLQ

## Non-scope

- NON crea la coda SQS (operatore → AWS console o CLI prima del deploy)
- NON implementa dead-letter queue (DLQ) — documentare configurazione consigliata
- NON modifica alert engine o dashboard
- NON aggiunge nuovi test PHPUnit (verifica è end-to-end manuale documentata)
- NON implementa EB worker tier (complessità non giustificata per free tier single-instance)

---

## Risk tier

**HIGH** — integrazione reale con SQS AWS; consume messaggi da coda produzione;
IAM permissions errate possono esporre o bloccare la coda.

---

## Allowed paths

```
src/Command/SqsPollCommand.php
.ebextensions/08_sqs.config
docs/sqs_worker.md
```

## Forbidden paths

```
src/Service/SqsPollerService.php   (nessuna modifica al service già funzionante — solo se strettamente necessario)
tests/                             (nessuna modifica ai test)
config/app.php                     (gestito da TASK_m4_rds_config e TASK_m4_env_vars)
src/Controller/                    (nessuna modifica ai controller)
```

---

## Dipendenze

```
BLOCKED_BY:  TASK_m4_rds_config   (DB deve essere configurato per persistere i messaggi)
BLOCKS:      N/A
Pre-check:   TASK_m4_rds_config status == DONE? → SÌ → pronto / NO → stato BLOCKED
Parallelo con: TASK_m4_healthcheck_aws (path disgiunti)
```

---

## DoD

```
[ ] SqsPollCommand legge SQS_QUEUE_URL da env (nessuna URL hardcoded)
[ ] .ebextensions/08_sqs.config presente: cron ogni minuto esegue php bin/cake sqs_poll
[ ] Test end-to-end: 1 messaggio inviato su coda reale → record in metrics DB → documentato in HANDOFF
[ ] eb logs mostra "SQS poll started" e "SQS poll completed" (correlation_id presente)
[ ] docs/sqs_worker.md presente (crea coda, IAM, DLQ consigliata, monitoraggio)
[ ] make test PASS (35/35 — nessuna regressione locale)
[ ] HANDOFF_m4_sqs_worker.md creato con correlation_id (UUID v4)
[ ] STATE.json aggiornato: status → DONE, last_updated → <ISO8601>
```

## Comandi verifica

```bash
# Verifica SqsPollCommand usa env var (no hardcoded URL)
grep -n "sqs\.amazonaws\.com\|queue_url\|QUEUE_URL" src/Command/SqsPollCommand.php

# Invia messaggio di test alla coda (AWS CLI)
aws sqs send-message \
  --queue-url "$SQS_QUEUE_URL" \
  --message-body '{"source":"test-ec2","name":"cpu_usage","value":55.5,"unit":"percent","tags":{},"recorded_at":"2026-04-12T10:00:00Z"}'

# Verifica log EB dopo il poll
eb logs | grep "SQS poll\|correlation_id"

# Verifica record persistito in DB
mysql -h <rds-endpoint> -u <user> -p sdi_ops_monitor \
  -e "SELECT source, name, value, created FROM metrics ORDER BY id DESC LIMIT 3;"

# Test suite locale
make test
```

---

## Assunzioni

- [A_M4_17] Coda SQS già creata dall'operatore prima di questo task (standard, non FIFO)
- [A_M4_18] IAM Role dell'istanza EC2 EB ha permessi `sqs:ReceiveMessage`, `sqs:DeleteMessage`,
            `sqs:GetQueueAttributes` sulla coda configurata (IAM Role preferito su env vars per sicurezza)
- [A_M4_19] Cron via `.ebextensions` è sufficiente per M4 (frequenza: 1 minuto); EB worker tier
            richiederà configurazione separata se si vuole gestione DLQ automatica in futuro
