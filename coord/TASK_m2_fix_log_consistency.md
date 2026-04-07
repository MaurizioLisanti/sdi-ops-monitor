# TASK_m2_fix_log_consistency — Fix W1 log format + W3 stale TODO

<!-- [CREATED: 2026-04-07 — Planner pass M2: W1 + W3 from INTEGRATION_REPORT_wave2] -->

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

Chiudere i due warning non bloccanti rilevati in INTEGRATION_REPORT_wave2:

- **W1** — Standardizzare i 4 Log calls in `MetricsController::handleSnsRequest()` al
  pattern `Log::*(json_encode([...]))` già usato in `ingestMetric()` e `AlertsService`.
- **W3** — Aggiornare il `TODO (Planner)` stale in `AppController.php:12`: rimuovere
  i riferimenti ad auth e correlation_id (già implementati via middleware in M1),
  mantenere solo il TODO per rate-limit non ancora implementato.

---

## Scope

- [x] `src/Controller/Api/MetricsController.php` — W1: standardizzare i 4 Log calls in
      `handleSnsRequest()` al pattern `json_encode`:
      - `Log::info(...)` per SubscriptionConfirmation (riga ~131)
      - `Log::warning(...)` per firma SNS non valida (riga ~145)
      - `Log::info(...)` per Notification accettata (riga ~162)
      - `Log::info(...)` per message type ignorato (riga ~171)
      Ogni entry deve includere: `timestamp`, `level`, `correlation_id`, `message`, `context`
- [x] `src/Controller/AppController.php` — W3: aggiornare il PHPDoc e il TODO inline:
      - Rimuovere `add auth component` (fatto: BasicAuthMiddleware M1)
      - Rimuovere `correlation_id injection` (fatto: CorrelationIdMiddleware M1)
      - Mantenere: `// TODO: add rate-limit middleware — auth and correlation_id handled via middleware (M1).`

## Non-scope

- [ ] NON modificare `AlertsService` — usa già json_encode in modo consistente
- [ ] NON cambiare comportamento o firma dei metodi
- [ ] NON toccare test esistenti — non cambia logica
- [ ] NON implementare rate-limit (rimandato a task dedicato)
- [ ] NON modificare file fuori dall'Allowed paths

---

## Risk tier

**LOW** — refactoring di soli Log calls e un commento. Nessun cambio di logica o
contratti I/O. I test esistenti non verificano il formato dei log, quindi non
ci sono regressioni attese.

---

## Allowed paths

```
src/Controller/Api/MetricsController.php
src/Controller/AppController.php
```

## Forbidden paths

```
src/Service/AlertsService.php          # già consistente — non toccare
src/Service/SnsSignatureValidator.php
src/Application.php
config/
tests/
coord/                                 # solo Planner/Reviewer
```

---

## Dipendenze

```
BLOCKED_BY: N/A
BLOCKS:     TASK_m2_log_viewer
            (il Log Viewer si aspetta formato json_encode consistente
             su tutti i log della pipeline SNS prima di essere implementato)

Pre-check:  N/A — task pronto per assegnazione immediata.

Parallelismo:
  Parallelo con TASK_m2_fix_sns_e2e_test (path disgiunti: src/Controller/Api/ e tests/).
  Parallelo con TASK_m2_dashboard_severity (path disgiunti).
```

---

## DoD

```bash
# Lint dei file modificati
php8.2 -l src/Controller/Api/MetricsController.php   # → No syntax errors
php8.2 -l src/Controller/AppController.php            # → No syntax errors

# Suite completa — zero regressioni
make test
# → OK (17 tests, 48 assertions) — exit 0
# Nota: il conteggio NON aumenta perché non si aggiungono test
```

**Criteri DONE:**
- [ ] Tutti i Log calls in `handleSnsRequest()` usano `json_encode([...], JSON_THROW_ON_ERROR)`
      con i campi `timestamp`, `level`, `correlation_id`, `message`, `context`
- [ ] Il formato è identico a quello di `ingestMetric()` e `AlertsService`
- [ ] Il TODO in `AppController.php:12` fa riferimento solo a rate-limit
- [ ] `make test` → exit 0, 17 tests (nessuna regressione)
- [ ] `coord/HANDOFF_m2_fix_log_consistency.md` creato con `correlation_id`

---

## Comandi verifica

```bash
php8.2 -l src/Controller/Api/MetricsController.php
php8.2 -l src/Controller/AppController.php
make test
```

---

## Assunzioni

- [A1] I test esistenti non verificano il payload delle Log calls — il refactoring
       è safe senza aggiungere nuovi test per questo task.
- [A2] JsonFormatter è configurato in `config/app_local.php` (responsabilità dell'operatore).
       Con o senza JsonFormatter, `json_encode()` garantisce output JSON uniforme.
