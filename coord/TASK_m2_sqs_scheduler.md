# TASK_m2_sqs_scheduler — AWS SQS polling scheduled Command

<!-- [CREATED: 2026-04-07 — Planner pass M2: feature SQS scheduler M2] -->

---

## Metadata

```
created:   2026-04-07T00:00:00Z
updated:   2026-04-07T00:00:00Z
assignee:  Executor
status:    TODO
wave:      3
milestone: M2
risk_tier: HIGH
```

---

## Obiettivo

Implementare un CakePHP 5 Console Command (`bin/cake sqs_poll`) che esegue il polling
di una coda AWS SQS, estrae i messaggi in formato metrica JSON, li valida e li persiste
tramite `MetricsTable` + `AlertsService::evaluate()` (stessa pipeline di `POST /api/metrics`).

Il command è pensato per essere eseguito via cron o scheduler esterno a intervallo fisso
(es. ogni 60 secondi). Ogni esecuzione processa un batch di messaggi (max 10, configurabile)
e termina.

---

## Scope

- [x] `composer.json` — aggiungere dipendenza `aws/aws-sdk-php: ^3.0` nella sezione
      `require`. Eseguire `composer require aws/aws-sdk-php:^3.0`.

- [x] `src/Command/SqsPollCommand.php` — nuovo CakePHP 5 Command:
      - Classe: `App\Command\SqsPollCommand extends \Cake\Command\Command`
      - `defaultName()`: `'sqs_poll'`
      - Usa `SqsPollerService` per il polling — NON istanzia SQS client direttamente
      - Log strutturato JSON (pattern `json_encode` come da W1 standard) con `correlation_id`
        generato per ogni run (`Cake\Utility\Text::uuid()`)
      - Output via `$io->out()` per uso CLI
      - Exit code: `Command::CODE_SUCCESS` (0) anche se la coda è vuota;
        `Command::CODE_ERROR` (1) solo su errori fatali (client non configurato, etc.)
      - Opzioni CLI: `--max-messages=10` (default: 10, max: 10 per SQS limit),
        `--dry-run` (parsa i messaggi senza salvarli, utile per debug)

- [x] `src/Service/SqsPollerService.php` — nuovo servizio:
      - Legge da env: `AWS_SQS_QUEUE_URL`, `AWS_REGION`, `AWS_ACCESS_KEY_ID`,
        `AWS_SECRET_ACCESS_KEY` (o `AWS_SESSION_TOKEN` per ruoli IAM temporanei)
      - Metodo `poll(int $maxMessages = 10): array` — restituisce array di `Metric` persistiti
      - Ogni messaggio SQS: `Body` deve essere JSON valido con campi metric standard
        (`source`, `name`, `value`, `unit`, `recorded_at`)
      - Messaggio invalido: log warning, delete-from-queue comunque (dead-letter policy SQS)
      - Messaggio valido: persiste via `MetricsTable`, chiama `AlertsService::evaluate()`
      - Delete from queue: solo dopo salvataggio riuscito; messaggio invalido eliminato
        comunque (non riprocessare payload non parsable)
      - PHPDoc completo su tutti i metodi pubblici

- [x] `config/app_local.php.example` — aggiungere sezione commentata con le variabili
      SQS necessarie:
      ```
      // AWS SQS Scheduler — set via environment variables, never hardcode credentials
      // AWS_SQS_QUEUE_URL=https://sqs.<region>.amazonaws.com/<account>/<queue-name>
      // AWS_REGION=eu-west-1
      // AWS_ACCESS_KEY_ID=<from IAM role or env>
      // AWS_SECRET_ACCESS_KEY=<from IAM role or env>
      ```

- [x] `tests/TestCase/Command/SqsPollCommandTest.php` — nuovo:
      - `testPollProcessesValidMessage` — mock SqsPollerService, verifica che
        un messaggio valido venga processato e il command ritorni CODE_SUCCESS
      - `testPollHandlesEmptyQueue` — mock service ritorna [], CODE_SUCCESS
      - `testPollWithDryRunDoesNotPersist` — con `--dry-run`, nessun save su DB

## Non-scope

- [ ] NON implementare SQS dead-letter queue (configurazione infrastruttura AWS)
- [ ] NON implementare retry logic — SQS gestisce la visibility timeout
- [ ] NON implementare scheduler interno CakePHP (usare cron esterno)
- [ ] NON aggiungere UI per configurare la coda
- [ ] NON implementare batch write a MetricsTable (1 save per messaggio)
- [ ] NON modificare MetricsController o AlertsService

---

## Risk tier

**HIGH** — integrazione con servizio AWS esterno, scrittura dati in produzione,
credentials in env vars. Richiede revisione umana prima del merge.
- Rischio credential leak: NON hardcodare chiavi nel codice o nei test
- Rischio cost explosion: polling interval troppo basso su code grandi → costo SQS
- Rischio data injection: messaggi SQS falsificati → mitigazione: stessa pipeline di validazione MetricsTable

---

## Allowed paths

```
composer.json
src/Command/SqsPollCommand.php
src/Service/SqsPollerService.php
config/app_local.php.example
tests/TestCase/Command/SqsPollCommandTest.php
```

## Forbidden paths

```
src/Controller/                    # non modificare controller esistenti
src/Service/AlertsService.php      # chiamato via dependency, non modificato
src/Application.php                # routing non necessario (command CLI)
config/app.php                     # NON inserire credentials qui
config/Migrations/                 # nessuna migration necessaria
coord/
```

---

## Dipendenze

```
BLOCKED_BY: N/A
BLOCKS:     TASK_m2_scenario_simulator
            (il simulatore SQS usa SqsPollerService come riferimento per
             il formato dei messaggi)

Pre-check:  N/A — task pronto per assegnazione immediata.
            NOTA: può essere eseguito in parallelo con TASK_m2_log_viewer
            (path disgiunti: src/Command/ e src/Service/SqsPollerService.php
             vs src/Controller/LogViewerController.php).
```

---

## DoD

```bash
# Installa dipendenza AWS SDK
composer require aws/aws-sdk-php:^3.0
# → Aggiornato composer.lock

# Lint
php8.2 -l src/Command/SqsPollCommand.php
php8.2 -l src/Service/SqsPollerService.php

# Test command (con mock SQS)
php8.2 vendor/bin/phpunit tests/TestCase/Command/SqsPollCommandTest.php --testdox
# → OK (3 tests) — exit 0

# Suite completa — nessuna regressione
make test
# → OK (≥ 20 tests) — exit 0

# Verifica CLI (environment di test con SQS reale o localstack opzionale)
AWS_SQS_QUEUE_URL=<url> AWS_REGION=eu-west-1 bin/cake sqs_poll --dry-run
# → log strutturato JSON, exit 0
```

**Criteri DONE:**
- [ ] `aws/aws-sdk-php` in `composer.json` e `composer.lock` aggiornati
- [ ] `SqsPollCommand` eseguibile via `bin/cake sqs_poll`
- [ ] `SqsPollerService::poll()` gestisce coda vuota, messaggio valido, messaggio invalido
- [ ] Credentials solo da env vars (nessuna hardcoded nel codice)
- [ ] `--dry-run` funzionante (nessun save su DB)
- [ ] Log strutturato JSON con `correlation_id` per ogni run
- [ ] `SqsPollCommandTest` → 3 test PASS (con mock SQS)
- [ ] `make test` → exit 0
- [ ] `config/app_local.php.example` aggiornato con sezione SQS commentata
- [ ] `coord/HANDOFF_m2_sqs_scheduler.md` creato con `correlation_id`
- [ ] Revisione umana del diff prima del merge (HIGH risk tier)

---

## Comandi verifica

```bash
php8.2 -l src/Command/SqsPollCommand.php
php8.2 -l src/Service/SqsPollerService.php
php8.2 vendor/bin/phpunit tests/TestCase/Command/SqsPollCommandTest.php --testdox
make test
```

---

## Assunzioni

- [A1] `aws/aws-sdk-php` ^3.0 è compatibile con PHP 8.2 — confermato dalla documentazione AWS SDK.
- [A2] Le AWS credentials vengono iniettate via env vars (EC2 instance role, ECS task role
       o variabili esplicite) — mai hardcodate nel codice o nei test.
- [A3] Un messaggio SQS con `Body` non parsabile come JSON viene eliminato dalla coda
       (no retry) e loggato come warning — comportamento documentato nel HANDOFF.
- [A4] Il cron esterno (systemd timer, AWS EventBridge Scheduler, ecc.) non è in scope
       di questo task — solo il command CakePHP. L'operatore configura il cron.
- [A5] La tabella `metrics` esiste (migration M0) — `SqsPollerService` usa `fetchTable('Metrics')`.
- [A6] I test usano mock di `SqsPollerService` (PHPUnit mock objects) — nessuna connessione
       SQS reale richiesta per `make test` PASS.
