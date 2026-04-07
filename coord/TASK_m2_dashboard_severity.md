# TASK_m2_dashboard_severity — Dashboard semaforo con logica severity-based

<!-- [CREATED: 2026-04-07 — Planner pass M2: feature dashboard M2] -->

---

## Metadata

```
created:   2026-04-07T00:00:00Z
updated:   2026-04-07T00:00:00Z
assignee:  Executor
status:    TODO
wave:      3
milestone: M2
risk_tier: LOW
```

---

## Obiettivo

Migliorare il semaforo della dashboard passando dalla logica count-based
(`green/yellow/red` in base al numero di alert aperti) a una logica **severity-based**:
il colore riflette la severità massima degli alert aperti, non il loro conteggio.

Aggiungere nella view una sezione che raggruppa gli alert per severity e mostra
il conteggio per livello.

---

## Scope

- [x] `src/Controller/DashboardController.php` — aggiornare `index()`:
      - Nuova logica traffic-light:
        ```
        critical presente   → red   ("CRITICAL")
        high presente       → red   ("CRITICAL")   [oppure yellow se preferito — vedi A1]
        medium presente     → yellow ("WARNING")
        low presente        → yellow ("WARNING")
        0 alert             → green  ("OK")
        ```
        **Regola finale adottata** (documentata in A1):
        ```
        red    : almeno 1 alert con severity IN ('critical', 'high')
        yellow : almeno 1 alert con severity IN ('medium', 'low') AND nessun critical/high
        green  : 0 alert aperti
        ```
      - Passare alla view anche `$alertsBySeverity`: array con conteggi per livello
        ```php
        $alertsBySeverity = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
        foreach ($openAlerts as $alert) {
            $alertsBySeverity[$alert->severity] = ($alertsBySeverity[$alert->severity] ?? 0) + 1;
        }
        ```
      - Aggiungere `$highestSeverity` (stringa: 'critical'|'high'|'medium'|'low'|null)
        per uso nella view.

- [x] `templates/Dashboard/index.php` — aggiornare:
      - Semaforo header: riflette il colore severity-based (già usa `$statusCss`)
      - Aggiungere sezione "Severity breakdown" tra le summary cards e la tabella alert:
        mini-card o badge row con contatori per critical/high/medium/low.
        Mostrare solo le severity con count > 0. Se nessun alert: sezione nascosta.
      - Ordinamento: la tabella alert già è ordinata da `AlertsTable::findOpen()`
        usando `FIELD(severity, 'critical','high','medium','low')` — nessuna modifica
        all'ordinamento necessaria.

- [x] `tests/TestCase/Controller/DashboardControllerTest.php` — aggiornare/aggiungere:
      - Verificare che il semaforo sia `red` quando il DB ha alert con severity `critical`
        (se i test fixture lo supportano) oppure aggiornare il test esistente per
        verificare che `$overallStatus` venga passato alla view con la nuova logica.
      - In alternativa: aggiungere test unit-style su DashboardController (mock tabella)
        se IntegrationTest non permette setup fixture adeguato.
        **NOTA:** se modificare i test richiede fixture complesse, limitare lo scope
        a refactoring del controller + view e documentare nel HANDOFF come
        `[TEST_DEFERRED: coverage via manuel verifica o fixture M2+]`.

## Non-scope

- [ ] NON implementare acknowledge/resolve alert (task dedicato futuro)
- [ ] NON aggiungere filtri/ricerca sulla dashboard
- [ ] NON modificare `AlertsTable::findOpen()` — già corretto con FIELD()
- [ ] NON modificare il layout Bootstrap base
- [ ] NON cambiare i contratti I/O di `GET /` (HTML response invariata)

---

## Risk tier

**LOW** — solo DashboardController::index() (logica PHP semplice) e template HTML.
Nessun cambio DB, nessun cambio routing, nessuna modifica a service esistenti.

---

## Allowed paths

```
src/Controller/DashboardController.php
templates/Dashboard/index.php
tests/TestCase/Controller/DashboardControllerTest.php
```

## Forbidden paths

```
src/Model/Table/AlertsTable.php    # findOpen() già corretto — non toccare
src/Service/AlertsService.php
src/Application.php
config/
coord/
```

---

## Dipendenze

```
BLOCKED_BY: N/A
BLOCKS:     N/A

Pre-check:  N/A — task pronto per assegnazione immediata.

Parallelismo:
  Parallelo con TASK_m2_fix_log_consistency (path disgiunti).
  Parallelo con TASK_m2_fix_sns_e2e_test (path disgiunti).
```

---

## DoD

```bash
# Lint
php8.2 -l src/Controller/DashboardController.php
php8.2 -l templates/Dashboard/index.php     # (file PHP, controllare sintassi)

# Test dashboard
php8.2 vendor/bin/phpunit tests/TestCase/Controller/DashboardControllerTest.php --testdox
# → OK (≥ 1 test) — DashboardControllerTest::testIndexReturns200 PASS

# Suite completa
make test
# → OK (≥ 17 tests, ≥ 48 assertions) — exit 0
```

**Criteri DONE:**
- [ ] Semaforo: `red` quando severity `critical` o `high` presente
- [ ] Semaforo: `yellow` quando solo `medium`/`low` presenti
- [ ] Semaforo: `green` quando 0 alert aperti
- [ ] View mostra sezione "Severity breakdown" con contatori per livello
- [ ] `DashboardControllerTest::testIndexReturns200` → PASS
- [ ] `make test` → exit 0
- [ ] `coord/HANDOFF_m2_dashboard_severity.md` creato con `correlation_id`

---

## Comandi verifica

```bash
php8.2 -l src/Controller/DashboardController.php
php8.2 vendor/bin/phpunit tests/TestCase/Controller/DashboardControllerTest.php --testdox
make test
```

---

## Assunzioni

- [A1] La regola `red = critical OR high` è la scelta di default. Se l'utente
       preferisce `red = critical only`, `yellow = high`, aggiornare in implementation.
       Documentare la scelta finale nel HANDOFF.
- [A2] `AlertsTable::findOpen()` usa già `FIELD()` per ordinamento semantic — la view
       eredita l'ordinamento corretto senza ulteriori modifiche alla query.
- [A3] I test di integrazione esistenti non mockano il DB per gli alert: se DashboardControllerTest
       non ha fixture per alert con severity specifica, limitare il test a `assertResponseCode(200)`
       e documentare la coverage gap nel HANDOFF.
