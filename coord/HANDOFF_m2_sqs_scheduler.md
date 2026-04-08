## HANDOFF_m2_sqs_scheduler.md

### Metadata
- task: TASK_m2_sqs_scheduler
- status: DONE
- correlation_id: a7e3b2d1-4f8c-4a9e-b1c2-d3e4f5a6b7c8
- run_id: run-20260408-001
- created: 2026-04-08T15:30:00Z
- branch: task/m2_sqs_scheduler
- agent: claude-sonnet-4-6

### Summary
Implemented `bin/cake sqs_poll` command + `SqsPollerService` for AWS SQS polling.
Valid metrics are persisted via MetricsTable and evaluated by AlertsService with
idempotence, explicit retry policy, and full dry-run support. 3 mock-only tests added.

### Files changed
- `composer.json` — modificato: aggiunto `aws/aws-sdk-php: ^3.0` in require
- `composer.lock` — modificato: dipendenze aws/aws-sdk-php + guzzle/psr7/promises aggiornate
- `src/Service/SqsPollerService.php` — aggiunto: servizio polling SQS con idempotenza e retry policy esplicita
- `src/Command/SqsPollCommand.php` — aggiunto: CakePHP 5 Command `sqs_poll` con opzioni --max-messages e --dry-run
- `config/app_local.php.example` — modificato: aggiunta sezione commentata variabili AWS SQS
- `tests/TestCase/Command/SqsPollCommandTest.php` — aggiunto: 3 test con mock SqsPollerService (nessuna connessione AWS reale)

### Commands run
```
php8.2 -l src/Command/SqsPollCommand.php      → PASS — No syntax errors detected
php8.2 -l src/Service/SqsPollerService.php    → PASS — No syntax errors detected
php8.2 vendor/bin/phpunit tests/TestCase/Command/SqsPollCommandTest.php --testdox
                                               → PASS — 3 tests, 6 assertions
make test                                      → PASS — 23 tests, 66 assertions
```

### Assunzioni fatte
- [A1] `aws/aws-sdk-php ^3.0` è compatibile con PHP 8.2 — confermato dall'installazione (installata versione 3.377.0).
- [A2] AWS credentials sono iniettate via env vars (EC2 instance role, ECS task role o variabili esplicite) — mai hardcodate nel codice o nei test.
- [A3] Un messaggio SQS con `Body` non parsabile come JSON, o con campi richiesti mancanti, viene eliminato dalla coda immediatamente (no retry) e loggato come warning — il payload è sempre non recuperabile.
- [A4] Il cron esterno (systemd timer, AWS EventBridge Scheduler, ecc.) non è in scope — solo il command CakePHP. Esempio cron incluso in `app_local.php.example`.
- [A5] La tabella `metrics` esiste (migration M0) — `SqsPollerService` usa `MetricsTable` iniettata via costruttore.
- [A6] I test usano mock completo di `SqsPollerService` — nessuna connessione SQS reale per `make test` PASS.
- [A7] `parseMessageBody()` e `isAlreadyPersisted()` e `deleteMessage()` sono stati resi `public` (anziché `private`) per consentire test unitari futuri sul servizio senza mocking completo.
- [A8] `Arguments::getOption()` in CakePHP 5 richiede `string|bool|null` — i test passano `'max-messages'` come stringa `'10'` (non int). Il command fa cast a `(int)` prima dell'uso.

### Rischi / TODO residui
- [RISK-HIGH] Questo task ha risk_tier HIGH — richiede revisione umana del diff prima del merge su main, come da AGENTS.md routing policy.
- [DLQ] La Dead Letter Queue non è implementata nel codice — è una configurazione infrastrutturale AWS da applicare sulla coda SQS (maxReceiveCount consigliato: 3–5). Documentata come best practice in `SqsPollerService` PHPDoc.
- [COST] Polling interval determinato dal cron esterno — un intervallo troppo basso su code grandi può generare costi SQS elevati. L'operatore è responsabile della configurazione dell'intervallo.
- [IDEMPOTENCE] Il controllo idempotenza usa `source + name + recorded_at` senza indice DB dedicato. Su volumi alti (>10k msg/giorno) valutare un indice composto su questi tre campi.
- [ALERT-FAILURE] Se `AlertsService::evaluate()` lancia un'eccezione, la metrica è comunque salvata e il messaggio eliminato dalla coda — il fallimento alert è best-effort e loggato come warning.
