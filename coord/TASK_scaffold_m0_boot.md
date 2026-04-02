# TASK_scaffold_m0_boot — CakePHP 5 boot + migrazioni

<!-- [UPDATED: 2026-04-02 — Planner pass: aggiunti metadata, allowed paths, BLOCKED_BY/BLOCKS, Non-scope, DoD completo con HANDOFF + correlation_id, comandi verifica stack-specifici] -->

---

## Metadata

```
created:  2026-04-02T00:00:00Z
updated:  2026-04-02T12:00:00Z
assignee: Executor
status:   TODO
wave:     1
milestone: M0
risk_tier: MED
```

---

## Obiettivo

Rendere l'applicazione CakePHP 5 avviabile con `make install && make up && make migrate`
su ambiente pulito. Tutti i file skeleton devono compilare senza errori PHP e le due
migration devono applicarsi senza errori su MySQL 8.0.

---

## Scope

- [x] `composer install` senza errori (dipendenze da composer.json)
- [x] `make migrate` applica `CreateMetricsTable` e `CreateAlertsTable` su MySQL 8.0
- [x] `php -l` su tutti i file in `src/` → nessun syntax error
- [x] `GET /` risponde (anche 500 con stack trace è accettabile — il routing deve partire)
- [x] `config/app_local.php` configurato da `.example` con credenziali DB locali

## Non-scope

- [ ] NON implementare business logic in Controller o Model (rinviata ai task successivi)
- [ ] NON configurare autenticazione (M1)
- [ ] NON modificare schema migration già definite — solo applicarle
- [ ] NON toccare Wave 2 (alert engine, AWS, observability)

---

## Risk tier

**MED** — bootstrap applicazione, modifiche a config/ e migration; no PII, no auth.

---

## Allowed paths

```
composer.json
composer.lock
Makefile
config/app.php
config/app_local.php
config/app_local.php.example
config/bootstrap.php
config/paths.php
config/routes.php
config/Migrations/20260402000001_CreateMetricsTable.php
config/Migrations/20260402000002_CreateAlertsTable.php
src/Application.php
src/Controller/AppController.php
src/Controller/HealthController.php
src/Controller/DashboardController.php
src/Controller/Api/MetricsController.php
src/Model/Entity/Metric.php
src/Model/Entity/Alert.php
src/Model/Table/MetricsTable.php
src/Model/Table/AlertsTable.php
templates/layout/default.php
```

## Forbidden paths

```
coord/          # solo Planner/Reviewer
.env            # non deve esistere — usare config/app_local.php
src/src/        # path duplicato legacy — non toccare
```

---

## Dipendenze

```
BLOCKED_BY: N/A  (primo task della wave)
BLOCKS:     TASK_m0_health_endpoint
            TASK_m0_metric_ingestion
            TASK_m0_dashboard

Pre-check:  N/A — pronto per avvio immediato
```

---

## DoD

```bash
# 1. Dipendenze installate
make install
# → exit 0, nessun errore composer

# 2. Migration applicate
make migrate
# → Applied 2 migrations (CreateMetricsTable, CreateAlertsTable)

# 3. Syntax check su tutti i file PHP rilevanti
php -l src/Application.php
php -l src/Controller/AppController.php
php -l src/Controller/DashboardController.php
php -l src/Controller/HealthController.php
php -l src/Controller/Api/MetricsController.php
php -l src/Model/Table/MetricsTable.php
php -l src/Model/Table/AlertsTable.php
# → No syntax errors detected (tutti i file)

# 4. App risponde
make up &
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/
# → qualsiasi HTTP code (non timeout/connrefused)
```

**Criteri DONE:**
- [ ] `make install` exit 0
- [ ] `make migrate` exit 0, 2 migration applicate
- [ ] Nessun syntax error in src/
- [ ] `GET /` risponde (qualsiasi status HTTP)
- [ ] `coord/HANDOFF_scaffold_m0_boot.md` creato con `correlation_id`
- [ ] diff summary incluso nel HANDOFF

---

## Comandi verifica (stack-specifici)

```bash
# Verifica migration applicate
vendor/bin/cake migrations status
# → 2 migrations: up

# Verifica tabelle create
mysql -u<user> -p<pass> sdi_ops_monitor -e "SHOW TABLES;"
# → metrics, alerts, phinxlog

# Verifica autoload classi CakePHP
vendor/bin/cake cache clear_all
# → exit 0
```

---

## Assunzioni

- [A1] MySQL 8.0 è up e raggiungibile prima di `make migrate`
- [A4] `config/app_local.php` viene copiato da `.example` e configurato dall'operatore prima dell'esecuzione — non generato automaticamente dall'agente
- [A5] CakePHP 5 non richiede un `config/bootstrap.php` separato — quello in `config/bootstrap.php` è sufficiente (risolve TODO Planner precedente)
