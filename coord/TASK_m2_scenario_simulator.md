# TASK_m2_scenario_simulator — SDI/FatturaPA scenario simulator

<!-- [CREATED: 2026-04-07 — Planner pass M2: feature scenario simulator M2] -->

---

## Metadata

```
created:   2026-04-07T00:00:00Z
updated:   2026-04-07T00:00:00Z
assignee:  Executor
status:    BLOCKED
wave:      3
milestone: M2
risk_tier: MED
```

---

## Obiettivo

Implementare un simulatore di scenari operativi SDI/FatturaPA accessibile via web
(`GET /simulate` per il form, `POST /simulate/run` per l'esecuzione).

L'operatore seleziona uno scenario predefinito, il simulatore inietta i metric events
corrispondenti direttamente tramite `MetricsTable` + `AlertsService` (bypassa HTTP,
usa la stessa logica della pipeline interna), e mostra i risultati: metriche inserite,
alert creati, log correlation_id per tracciabilità.

Destinazione d'uso: demo, test di regressione manuali, verifica della pipeline end-to-end
in ambienti non-produzione.

---

## Scope

- [x] `src/Service/ScenarioService.php` — nuovo servizio:
      - Catalogo scenari statici (array di config — non persistiti in DB):
        ```
        Scenario 1: "CPU Spike — SDI Batch Processing"
          → 3 metriche: cpu_usage=55 (ok), cpu_usage=82 (high alert), cpu_usage=97 (critical)
          → Atteso: 2 alert (high + critical)

        Scenario 2: "Memory Pressure — FatturaPA Validation"
          → 2 metriche: memory_usage=70 (ok), memory_usage=92 (high alert)
          → Atteso: 1 alert (high)

        Scenario 3: "Normal Operation — All Clear"
          → 3 metriche: cpu_usage=30, memory_usage=50, cpu_usage=40
          → Atteso: 0 alert

        Scenario 4: "FatturaPA Batch Failure Spike"
          → 4 metriche: cpu_usage=65, memory_usage=88 (high), cpu_usage=96 (critical),
                         memory_usage=97 (critical)
          → Atteso: 3 alert (1 high + 2 critical)
        ```
      - Metodo `getScenarios(): array` — restituisce catalogo per la view
      - Metodo `run(string $scenarioId, bool $dryRun = false): ScenarioResult`
        - `ScenarioResult`: classe semplice con campi `metricsInserted`, `alertsCreated`,
          `correlationId`, `log[]` (messaggi operazione)
        - `$dryRun = true`: simula senza salvare in DB (mostra cosa accadrebbe)
        - Usa `MetricsTable::save()` + `AlertsService::evaluate()` direttamente
        - PHPDoc completo

- [x] `src/Controller/ScenarioSimulatorController.php` — nuovo controller:
      - `index()` — GET `/simulate`: mostra form con selezione scenario + opzione dry-run
      - `run()` — POST `/simulate/run`: esegue lo scenario, mostra risultati
        (metriche inserite, alert creati, correlation_id del run, eventuali errori)
      - Protezione: BasicAuth (automatica via middleware stack)
      - Validazione input: scenario_id deve essere in lista valida (no arbitrary injection)
      - CSRF token (IntegrationTestTrait lo gestisce automaticamente nei test)

- [x] `templates/ScenarioSimulator/index.php` — form scenario selection:
      - Lista scenari con descrizione e "expected outcome"
      - Checkbox "Dry run (no DB write)"
      - Submit button "Run Scenario"

- [x] `templates/ScenarioSimulator/run.php` — risultati esecuzione:
      - Tabella metriche inserite (source, name, value, recorded_at)
      - Tabella alert creati (severity badge, message)
      - Correlation ID del run (per ricercare nei log via Log Viewer)
      - Link "Run again" e "View Dashboard"
      - Se dry-run: banner "DRY RUN — no data persisted"

- [x] `src/Application.php` — aggiungere routes:
      ```php
      $builder->connect('/simulate', ['controller' => 'ScenarioSimulator', 'action' => 'index']);
      $builder->connect('/simulate/run', ['controller' => 'ScenarioSimulator', 'action' => 'run']);
      ```

- [x] `tests/TestCase/Controller/ScenarioSimulatorControllerTest.php` — nuovo:
      - `testIndexReturns200` — GET `/simulate` con auth → 200
      - `testRunReturns200WithValidScenario` — POST `/simulate/run` con scenario_id valido → 200
      - `testRunRejectsMissingScenarioId` — POST senza scenario_id → 422 o redirect con flash error
      - `testRunDryRunDoesNotPersist` — POST con `dry_run=1` → 200, no Metric in DB

## Non-scope

- [ ] NON implementare scenari custom via form (solo scenari predefiniti)
- [ ] NON implementare scenario replay da log
- [ ] NON modificare MetricsController o AlertsService
- [ ] NON aggiungere migration (nessun nuovo DB schema)
- [ ] NON supportare scenari SQS live (simulazione interna diretta, non via SQS)
- [ ] NON esporre `/simulate` in produzione senza protezione aggiuntiva (BasicAuth è sufficiente per M2)

---

## Risk tier

**MED** — scrive dati in DB di produzione se usato in ambiente prod. L'operatore deve
usare il dry-run flag per validare prima. Il controller è protetto da BasicAuth.
Input validation su `scenario_id` è obbligatoria (no path traversal, no injection).

---

## Allowed paths

```
src/Service/ScenarioService.php
src/Controller/ScenarioSimulatorController.php
templates/ScenarioSimulator/index.php
templates/ScenarioSimulator/run.php
tests/TestCase/Controller/ScenarioSimulatorControllerTest.php
src/Application.php
```

## Forbidden paths

```
src/Service/AlertsService.php      # chiamato via dependency, non modificato
src/Service/SqsPollerService.php   # non modificare
src/Controller/Api/MetricsController.php
config/Migrations/
coord/
```

---

## Dipendenze

```
BLOCKED_BY: TASK_m2_sqs_scheduler
            (SqsPollerService introduce il pattern per il formato metrica SQS
             — il simulatore lo usa come riferimento per i payload di Scenario 4)
            TASK_m2_log_viewer
            (entrambi toccano Application.php per la registrazione route
             — serializzare per evitare merge conflict; log_viewer va prima)

BLOCKS:     N/A

Pre-check:  TASK_m2_sqs_scheduler DONE? NO → BLOCKED
            TASK_m2_log_viewer DONE?    NO → BLOCKED
            Entrambi DONE → pronto per assegnazione

Sequenza esecuzione Wave 3:
  Step 1 (par): fix_log_consistency, fix_sns_e2e_test, dashboard_severity
  Step 2 (par): sqs_scheduler, log_viewer
  Step 3 (seq): scenario_simulator
```

---

## DoD

```bash
# Lint
php8.2 -l src/Service/ScenarioService.php
php8.2 -l src/Controller/ScenarioSimulatorController.php

# Test controller
php8.2 vendor/bin/phpunit tests/TestCase/Controller/ScenarioSimulatorControllerTest.php --testdox
# → OK (4 tests) — exit 0

# Suite completa
make test
# → OK (≥ 24 tests) — exit 0
```

**Criteri DONE:**
- [ ] `GET /simulate` → 200 con form scenari (BasicAuth richiesto)
- [ ] `POST /simulate/run` con scenario valido → 200 con risultati (metriche, alert, correlation_id)
- [ ] `POST /simulate/run` con `dry_run=1` → 200, nessun record in DB
- [ ] Scenario 1 "CPU Spike" → crea 2 alert (high + critical) nel DB
- [ ] Input `scenario_id` validato contro lista ammessa (no injection)
- [ ] Correlation ID del run mostrato nella view (ricercabile in Log Viewer)
- [ ] `ScenarioSimulatorControllerTest` → 4 test PASS
- [ ] `make test` → exit 0
- [ ] `coord/HANDOFF_m2_scenario_simulator.md` creato con `correlation_id`

---

## Comandi verifica

```bash
php8.2 -l src/Service/ScenarioService.php
php8.2 -l src/Controller/ScenarioSimulatorController.php
php8.2 vendor/bin/phpunit tests/TestCase/Controller/ScenarioSimulatorControllerTest.php --testdox
make test
```

---

## Assunzioni

- [A1] I 4 scenari predefiniti sono hardcoded in `ScenarioService` — nessuna configurazione
       DB necessaria. Aggiunta di nuovi scenari = modifica del codice (non config).
- [A2] Il simulatore usa `MetricsTable::save()` + `AlertsService::evaluate()` direttamente —
       bypassa il controller HTTP. Le validazioni `MetricsTable` si applicano ugualmente.
- [A3] I dati inseriti dal simulatore in produzione sono reali alert/metriche — l'operatore
       usa il dry-run per ambienti prod o prepara la dismissione degli alert post-simulazione.
- [A4] `src/Application.php` ha già una route `/logs` aggiunta da TASK_m2_log_viewer —
       questo task aggiunge solo le route `/simulate` senza rimuovere quelle esistenti.
- [A5] La `ScenarioResult` è una semplice classe PHP 8.2 readonly (o array associativo)
       senza ORM — non persistita in DB.
