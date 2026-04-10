---
task_id: TASK_m3_fix_wave3_obs
created: 2026-04-10T18:00:00Z
updated: 2026-04-10T18:00:00Z
milestone: M3
assignee: Executor
suggested_agent: Claude
status: BLOCKED
risk_tier: LOW
correlation_id_template: "UUID-v4 generato al momento della creazione HANDOFF"
---

# TASK_m3_fix_wave3_obs — Fix Osservazioni Residue Integration Report Wave 3

## Obiettivo

Risolvere le 4 osservazioni non bloccanti segnalate in
`coord/INTEGRATION_REPORT_wave3.md` (OBS-1…OBS-4) per portare il
codebase allo standard di qualità richiesto per la demo di colloquio.

---

## Scope

### OBS-1 — Application.php docstring stale

- [ ] `src/Application.php`, metodo `routes()` (circa riga 110):
      aggiornare il docstring da "then appends the /logs route"
      a elenco completo di tutte le route aggiunte in M2 e M3
      (`/logs`, `/simulate`, `/simulate/run`, `/ai-diagnostics`)

### OBS-2 — ScenarioResult accoppiata in ScenarioService.php

- [ ] Estrarre la classe `ScenarioResult` da `src/Service/ScenarioService.php`
      in file dedicato `src/Model/ScenarioResult.php`
- [ ] Aggiornare `src/Service/ScenarioService.php` con `use App\Model\ScenarioResult;`
- [ ] Verificare che i test `ScenarioSimulatorControllerTest` non referenzino
      direttamente `ScenarioResult` (se sì, aggiornare `use` statement)

### OBS-3 — Typo nel source identifier SDI

- [ ] `src/Service/ScenarioService.php`: correggere `fattturapa-validator-roma-01`
      (tripla 't') → `fatturapa-validator-roma-01`
- [ ] Verificare nessuna altra occorrenza del typo nel codebase

### OBS-4 — LogViewerController memory footprint

- [ ] `src/Controller/LogViewerController.php`, metodo `readLastLines()`:
      sostituire `file()` (carica intero file in memoria) con
      `SplFileObject` + `seek()` o lettura dal fondo con buffer
      per evitare OOM su file di log > 200 MiB
- [ ] Il comportamento esterno del metodo deve restare invariato
      (restituisce le ultime N righe, stesso formato)

## Non-scope

- [ ] NON aggiungere nuove funzionalità
- [ ] NON toccare test se non strettamente necessario per OBS-2
- [ ] NON modificare altri file oltre ai 6 path elencati
- [ ] NON fixare altri code smell non elencati in OBS-1…OBS-4

---

## Risk tier: LOW

- Tutti e 4 i fix sono chore/refactor senza nuova logica di business
- OBS-2: rename di classe interna — nessun impatto su contratti API
- OBS-4: refactor algoritmo in metodo privato — comportamento esterno invariato

---

## Allowed paths

```
src/Application.php
src/Service/ScenarioService.php
src/Model/ScenarioResult.php
src/Controller/LogViewerController.php
tests/TestCase/Controller/LogViewerControllerTest.php
tests/TestCase/Controller/ScenarioSimulatorControllerTest.php
```

## Forbidden paths

```
src/Controller/MetricsController.php
src/Controller/AiDiagnosticsController.php
src/Service/AiDiagnosticsService.php
src/Middleware/
config/
templates/
```

---

## Dipendenze

- **BLOCKED_BY**: TASK_m3_ai_diagnostics
  (`src/Application.php` — ai_diagnostics aggiunge la route `/ai-diagnostics`;
   OBS-1 deve documentare quella route nel docstring → fix docstring va dopo)
- **BLOCKS**: N/A
- **Pre-check**: TASK_m3_ai_diagnostics DONE? → **NO** (attualmente TODO) → stato: **BLOCKED**

---

## DoD

- [ ] `make test` PASS — 30+ test, nessuna regressione
- [ ] OBS-1: docstring `routes()` riflette tutte le route attuali (M2 + M3)
- [ ] OBS-2: `src/Model/ScenarioResult.php` esiste come file separato;
      `ScenarioService.php` usa `use App\Model\ScenarioResult;`
- [ ] OBS-3: `grep -r "fattturapa" src/` → zero risultati
- [ ] OBS-4: `readLastLines()` usa `SplFileObject` — nessuna chiamata a `file()`
      per lettura log
- [ ] `coord/HANDOFF_m3_fix_wave3_obs.md` creato con `correlation_id` UUID v4
- [ ] diff summary nel HANDOFF

---

## Comandi verifica

```bash
# Test suite — nessuna regressione
make test

# OBS-3: verifica typo corretto
grep -rn "fattturapa" src/ && echo "TYPO ANCORA PRESENTE" || echo "OBS-3 OK"

# OBS-2: verifica file estratto
test -f src/Model/ScenarioResult.php && echo "OBS-2 file OK" || echo "OBS-2 MANCANTE"

# OBS-4: nessun file() in LogViewerController per lettura log
grep -n "= file(" src/Controller/LogViewerController.php \
  && echo "OBS-4 PENDING" || echo "OBS-4 OK"

# OBS-1: docstring aggiornata
grep -A5 "routes()" src/Application.php | grep "ai-diagnostics" \
  && echo "OBS-1 OK" || echo "OBS-1 PENDING"
```

---

## Assunzioni

- [A1] `src/Model/ScenarioResult.php` userà namespace `App\Model` — già presente
        nella struttura CakePHP 5, nessuna configurazione aggiuntiva necessaria
- [A2] I test `ScenarioSimulatorControllerTest` non importano `ScenarioResult`
        direttamente (lo usano attraverso il controller) — refactor OBS-2 trasparente
- [A3] `SplFileObject` disponibile in PHP 8.2 — nessuna dipendenza aggiuntiva
- [A4] Il typo OBS-3 non è referenziato in fixture di test — correzione sicura
