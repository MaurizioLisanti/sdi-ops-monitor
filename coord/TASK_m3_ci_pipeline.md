---
task_id: TASK_m3_ci_pipeline
created: 2026-04-10T18:00:00Z
updated: 2026-04-10T18:00:00Z
milestone: M3
assignee: Executor
suggested_agent: Claude
status: TODO
risk_tier: LOW
correlation_id_template: "UUID-v4 generato al momento della creazione HANDOFF"
---

# TASK_m3_ci_pipeline — GitHub Actions CI Pipeline

## Obiettivo

Creare `.github/workflows/ci.yml` che esegue automaticamente `composer install`,
setup MySQL 8.0 service, migrations e `make test` su ogni push e PR verso `main`.
Il workflow deve passare verde sullo stato attuale del repo (30/30 test PASS).

---

## Scope

- [ ] `.github/workflows/ci.yml`: trigger `on: [push, pull_request]` → branch `main`
- [ ] Job `build`: PHP 8.2 setup, MySQL 8.0 service container, composer install con cache,
      `make migrate`, `make test`
- [ ] Cache delle dipendenze Composer (chiave basata su `composer.lock`) per velocità
- [ ] Step nominati leggibili per debug rapido su GitHub Actions UI
- [ ] Variabili env nel workflow: `DATABASE_URL` per MySQL test con credenziali fisse
      (non secret — solo per CI environment)
- [ ] README.md: aggiungere badge CI se il file esiste (non creare README se assente)

## Non-scope

- [ ] NON aggiungere deploy step (CI only — nessun CD in M3)
- [ ] NON aggiungere Docker build step
- [ ] NON modificare codice PHP applicativo
- [ ] NON aggiungere PHPCS step (task separato: TASK_m3_phpcs)
- [ ] NON aggiungere step di security scan (out of scope M3)

---

## Risk tier: LOW

- Solo file di configurazione CI — nessun cambiamento al codice applicativo
- Worst case: workflow fallisce su GitHub Actions, zero impatto locale
- Nessuna credenziale reale — credenziali DB fisse solo per CI environment

---

## Allowed paths

```
.github/workflows/ci.yml
README.md
```

## Forbidden paths

```
src/
tests/
config/
templates/
composer.json
Makefile
```

---

## Dipendenze

- **BLOCKED_BY**: N/A
- **BLOCKS**: TASK_m3_phpcs
  (phpcs aggiungerà il proprio step a `ci.yml` già esistente)
- **Pre-check**: tutti BLOCKED_BY DONE? → **SÌ** → stato: **TODO** (pronto)

---

## DoD

- [ ] `.github/workflows/ci.yml` creato, sintassi YAML valida
- [ ] Workflow include: checkout, PHP 8.2, MySQL 8.0 service, composer install,
      `make migrate`, `make test`
- [ ] Composer cache configurata con chiave `${{ hashFiles('composer.lock') }}`
- [ ] Variabile `DATABASE_URL` (o equivalente env CakePHP) definita nel workflow
        per connessione al service MySQL
- [ ] Il workflow NON usa `continue-on-error: true` sul step `make test`
- [ ] `coord/HANDOFF_m3_ci_pipeline.md` creato con `correlation_id` UUID v4
- [ ] diff summary nel HANDOFF

---

## Comandi verifica

```bash
# Verifica sintassi YAML locale
python3 -c "import yaml; yaml.safe_load(open('.github/workflows/ci.yml'))" \
  && echo "YAML syntax OK"

# Conteggio step nel workflow
grep -c "name:" .github/workflows/ci.yml
# → atteso ≥ 5 step nominati

# Verifica presenza chiave cache Composer
grep "hashFiles" .github/workflows/ci.yml && echo "Cache OK"
```

---

## Assunzioni

- [A1] GitHub repository ha Actions abilitato (standard per tutti i repo)
- [A2] Credenziali DB nel workflow sono fisse per CI (`root` / password vuota o `secret`)
        — non secret GitHub, non esposte a runtime prod
- [A3] `make migrate` esegue le CakePHP migrations — usa la `DATABASE_URL` iniettata dal workflow
- [A4] PHP 8.2 e MySQL 8.0 corrispondono allo stack dichiarato in SPEC.md
- [A5] Il workflow non deve necessariamente eseguire in questa sessione —
        validazione sintattica YAML locale è sufficiente per il DoD
