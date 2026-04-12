# TASK_m4_env_vars — EB Environment Variables (secrets management)

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

Documentare e configurare tutte le variabili d'ambiente necessarie per il deployment
su Elastic Beanstalk, garantendo che **nessuna credenziale** sia committata nel repo.
Le var sensibili vengono iniettate via EB console; quelle non-sensibili via `.ebextensions`.
Exit: `config/app.php.example` aggiornato, `docs/env_vars.md` presente, zero secrets nel repo.

---

## Scope

- [ ] Definire lista completa env vars richieste (vedere sezione Env Vars Reference)
- [ ] `config/app.php.example` → aggiornare placeholder con nomi env vars standard
- [ ] `.ebextensions/03_env.config` → solo var non-sensibili (CAKE_ENV, APP_DEBUG, LOG_LEVEL)
- [ ] `docs/env_vars.md` → guida configurazione per operatore: quali var, dove impostarle, formato
- [ ] Verifica `grep -r "aws_secret\|db_password" config/ src/` → zero match (no hardcoded secrets)

### Env Vars Reference (da documentare)

```
# Database (RDS)
DATABASE_URL=mysql://user:pass@rds-host:3306/sdi_ops_monitor

# AWS (EB Instance Role consigliato — altrimenti env vars)
AWS_DEFAULT_REGION=eu-west-1
SQS_QUEUE_URL=https://sqs.eu-west-1.amazonaws.com/<account>/<queue>

# App
APP_KEY=<random-32-chars>           # CakePHP Security.salt
CAKE_ENV=production
APP_FULL_BASE_URL=https://<eb-url>
LOG_LEVEL=warning                   # production: warning; staging: debug

# External integrations
OPENROUTER_API_KEY=<optional>       # AI diagnostics — se assente: fallback deterministico
```

## Non-scope

- NON inserisce credenziali reali nel repo (mai)
- NON crea la coda SQS (infra AWS — fuori scope codice)
- NON modifica logica applicativa (`src/`)
- NON tocca file di test

---

## Risk tier

**HIGH** — gestione secrets; errore può esporre credenziali nel repo o rompere il deploy.

---

## Allowed paths

```
config/app.php.example
.ebextensions/03_env.config
docs/env_vars.md
```

## Forbidden paths

```
config/app.php        (file locale — non committato; gestito da .gitignore)
.env                  (non supportato su EB direttamente)
src/                  (nessuna modifica applicativa)
tests/
```

---

## Dipendenze

```
BLOCKED_BY:  TASK_m4_eb_infra
BLOCKS:      TASK_m4_rds_config
Pre-check:   TASK_m4_eb_infra status == DONE? → SÌ → pronto / NO → stato BLOCKED
```

---

## DoD

```
[ ] config/app.php.example aggiornato con tutti i placeholder env vars
[ ] .ebextensions/03_env.config presente (solo var non-sensibili)
[ ] docs/env_vars.md presente con guida operativa
[ ] grep -r "aws_secret\|db_password\|api_key=" config/ src/ → zero match
[ ] make test PASS (35/35 — nessuna regressione locale)
[ ] HANDOFF_m4_env_vars.md creato con correlation_id (UUID v4)
[ ] STATE.json aggiornato: status → DONE, last_updated → <ISO8601>
```

## Comandi verifica

```bash
# Zero secrets hardcoded nel repo
grep -rni "aws_secret_access_key\|db_password\s*=\s*['\"][^$]" config/ src/ \
  && echo "FAIL: hardcoded secret found" || echo "PASS"

# Verifica placeholder presenti in app.php.example
grep "DATABASE_URL\|APP_KEY\|SQS_QUEUE_URL" config/app.php.example \
  && echo "PASS: env vars referenced" || echo "FAIL: missing references"

# Test suite locale invariata
make test
```

---

## Assunzioni

- [A_M4_2] AWS credentials configurate in `~/.aws/credentials` lato operatore (non nel repo)
- [A_M4_6] Le variabili sensibili vengono impostate via EB console (Environment properties) — non via `.ebextensions`
- [A_M4_7] CakePHP 5 legge DATABASE_URL via `ConnectionManager` — `config/app.php` deve essere aggiornato per leggerlo
