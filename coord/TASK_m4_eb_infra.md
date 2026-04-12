# TASK_m4_eb_infra — EB Environment Scaffold (PHP 8.2, no Docker)

## Metadata

```
created:   2026-04-12T00:00:00Z
updated:   2026-04-12T00:00:00Z
assignee:  Executor
status:    TODO
milestone: M4
wave:      5
```

---

## Obiettivo

Inizializzare l'ambiente Elastic Beanstalk con platform PHP 8.2 (direct deploy, no Docker),
creare `.elasticbeanstalk/config.yml`, Procfile per il web process Apache/Nginx,
e le `.ebextensions` di scaffolding per le configurazioni successive.
Exit: `eb status` mostra `Health: Ok` e `GET /health` risponde 200 su URL EB pubblico.

---

## Scope

- [ ] `eb init` → applicazione `sdi-ops-monitor`, platform `PHP 8.2 running on 64bit Amazon Linux 2023`
- [ ] `eb create sdi-ops-monitor-prod` (free tier: t3.micro, single-instance)
- [ ] `.elasticbeanstalk/config.yml` committato (senza credenziali)
- [ ] `Procfile` → `web: bin/cake server -H 0.0.0.0 -p 8080` (o Apache default)
- [ ] `.ebextensions/01_php.config` → `memory_limit = 256M`, `max_execution_time = 30`, `upload_max_filesize = 8M`
- [ ] `.ebextensions/02_composer.config` → `composer install --no-dev --optimize-autoloader`
- [ ] `docs/aws_deploy.md` → istruzioni step-by-step deploy (eb init, eb deploy, eb logs)
- [ ] `.gitignore` aggiornato: aggiungere `.elasticbeanstalk/` (tranne `config.yml`)

## Non-scope

- NON configura RDS → TASK_m4_rds_config
- NON definisce env vars produzione → TASK_m4_env_vars
- NON configura CloudWatch → TASK_m4_cloudwatch
- NON tocca codice applicativo (`src/`, `tests/`)
- NON genera certificati SSL → TASK_m4_govway_mtls

---

## Risk tier

**MED** — deploy su infrastruttura AWS reale; nessun dato sensibile nel repo; reversibile.

---

## Allowed paths

```
.elasticbeanstalk/config.yml
.ebextensions/01_php.config
.ebextensions/02_composer.config
Procfile
.gitignore
docs/aws_deploy.md
```

## Forbidden paths

```
src/          (nessuna modifica applicativa)
tests/        (nessuna modifica ai test)
config/app.php  (gestito da TASK_m4_env_vars e TASK_m4_rds_config)
```

---

## Dipendenze

```
BLOCKED_BY:  N/A
BLOCKS:      TASK_m4_env_vars
             TASK_m4_cloudwatch
             TASK_m4_govway_mtls
             TASK_m4_rds_config    (indirettamente — via env_vars)
             TASK_m4_sqs_worker    (indirettamente — via rds_config)
             TASK_m4_healthcheck_aws (indirettamente — via rds_config)
Pre-check:   N/A — primo task della wave
```

---

## DoD

```
[ ] eb status → Health: Ok, Status: Ready
[ ] curl https://<eb-url>/health → 200 {"status":"ok"}
[ ] make test PASS (35/35 — nessuna regressione locale)
[ ] .elasticbeanstalk/config.yml presente nel repo (senza credenziali)
[ ] Procfile presente e correttamente configurato
[ ] .ebextensions/01_php.config e 02_composer.config presenti
[ ] docs/aws_deploy.md presente con istruzioni operative
[ ] HANDOFF_m4_eb_infra.md creato con correlation_id (UUID v4)
[ ] STATE.json aggiornato: status → DONE, last_updated → <ISO8601>
```

## Comandi verifica

```bash
# Verifica stato EB
eb status

# Verifica health check remoto
curl -s https://$(eb status | grep CNAME | awk '{print $2}')/health

# Verifica test suite locale (nessuna regressione)
make test

# Verifica config.yml non contiene credenziali
grep -i "aws_access\|aws_secret\|password\|token" .elasticbeanstalk/config.yml && echo "FAIL: secrets found" || echo "PASS: no secrets"
```

---

## Assunzioni

- [A_M4_1] Account AWS free tier disponibile con permessi EB, EC2, S3, IAM
- [A_M4_2] EB CLI installato localmente (`pip install awsebcli`)
- [A_M4_3] AWS credentials configurate in `~/.aws/credentials` o via env vars (non nel repo)
- [A_M4_4] Platform target: `PHP 8.2 running on 64bit Amazon Linux 2023` (no Docker — deploy diretto)
- [A_M4_5] Instance type: `t3.micro` (free tier eligible) — single-instance environment
