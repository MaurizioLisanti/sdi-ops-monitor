# TASK_m4_rds_config — RDS MySQL 8.0 Configuration + Migration on AWS

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

Configurare l'istanza RDS MySQL 8.0 (free tier), collegare l'applicazione CakePHP 5
tramite `DATABASE_URL` env var, eseguire le migration CakePHP su AWS.
Exit: `GET /health` su EB risponde 200 con DB raggiungibile; `make test` PASS locale.

---

## Scope

- [ ] Provisioning RDS MySQL 8.0 (db.t3.micro, free tier, Single-AZ, no Multi-AZ)
- [ ] Security group: EB EC2 instance → RDS port 3306 (ingress solo da SG EB)
- [ ] `config/app.php` → lettura `DATABASE_URL` da env var (aggiorna se non già presente)
- [ ] `config/app.php.example` → esempio DATABASE_URL con formato mysql://...
- [ ] `.ebextensions/04_rds.config` → hook post-deploy: `php bin/cake migrations migrate`
- [ ] Verifica migration eseguita su AWS: `SELECT * FROM phinxlog` ha righe

## Non-scope

- NON modifica schema delle migration esistenti (M0–M3)
- NON implementa connection pooling avanzato (RDS Proxy — fuori scope free tier)
- NON tocca dashboard, controller, alert engine
- NON crea nuovi test (solo verifica)
- NON gestisce backup RDS (console AWS — operatore)

---

## Risk tier

**MED** — scrittura su `config/app.php` (file critico); migration distruttiva se mal configurata.

---

## Allowed paths

```
config/app.php
config/app.php.example
.ebextensions/04_rds.config
```

## Forbidden paths

```
src/Model/Table/           (nessuna modifica alle tabelle)
config/Migrations/         (nessuna modifica alle migration)
tests/                     (nessuna modifica ai test)
```

---

## Dipendenze

```
BLOCKED_BY:  TASK_m4_env_vars   (DATABASE_URL deve essere definita prima)
BLOCKS:      TASK_m4_sqs_worker
             TASK_m4_healthcheck_aws
Pre-check:   TASK_m4_env_vars status == DONE? → SÌ → pronto / NO → stato BLOCKED
```

---

## DoD

```
[ ] RDS istanza AVAILABLE nella console AWS
[ ] Security group: solo SG dell'ambiente EB può raggiungere porta 3306
[ ] config/app.php legge DATABASE_URL da env (nessuna credenziale hardcoded)
[ ] .ebextensions/04_rds.config esegue migration al deploy
[ ] curl https://<eb-url>/health → 200 {"status":"ok","db":"ok"}
    (se /health non verifica DB separatamente: verificare che dashboard carichi senza errori DB)
[ ] SELECT COUNT(*) FROM phinxlog → righe presenti (migration eseguita)
[ ] make test PASS (35/35 — nessuna regressione locale)
[ ] HANDOFF_m4_rds_config.md creato con correlation_id (UUID v4)
[ ] STATE.json aggiornato: status → DONE, last_updated → <ISO8601>
```

## Comandi verifica

```bash
# Verifica /health su AWS (sostituire <eb-url>)
curl -s https://<eb-url>/health | python3 -m json.tool

# Verifica migration su RDS (richiede mysql client con accesso da bastion o EB SSH)
mysql -h <rds-endpoint> -u <user> -p sdi_ops_monitor \
  -e "SELECT migration_name, start_time FROM phinxlog ORDER BY start_time DESC LIMIT 5;"

# Verifica no hardcoded credentials
grep -n "password\|dbname" config/app.php | grep -v "env(\|getenv(\|\$_ENV\|\$_SERVER" \
  && echo "FAIL: hardcoded db config" || echo "PASS"

# Test suite locale
make test
```

---

## Assunzioni

- [A_M4_1] Account AWS free tier con permessi RDS, VPC, Security Groups
- [A_M4_8] RDS istanza: db.t3.micro, MySQL 8.0, storage 20GB gp2 (free tier limits)
- [A_M4_9] La VPC default è sufficiente per collegare EB e RDS; no VPC custom richiesta
- [A_M4_10] CakePHP 5 `ConnectionManager` supporta DATABASE_URL formato standard — da verificare; se necessario leggere singole env vars (DB_HOST, DB_NAME, DB_USER, DB_PASS)
