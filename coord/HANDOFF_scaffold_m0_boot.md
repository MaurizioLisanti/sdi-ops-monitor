## HANDOFF_scaffold_m0_boot.md

### Metadata
- task: TASK_scaffold_m0_boot
- status: DONE
- correlation_id: 7b3e9a14-2f5c-4d8e-a6b1-c9d0e3f4a5b2
- run_id: run-20260402-001
- created: 2026-04-02T12:30:00Z
- branch: main
- agent: claude-sonnet-4-6

### Summary
Applicazione CakePHP 5 resa avviabile: corretti tre difetti blocco-boot (PHP binary, middleware mancante, componente rimosso in CakePHP 5). Migrations CreateMetricsTable e CreateAlertsTable applicate su MySQL 8.0. `GET /` risponde HTTP 200.

### Files changed
- Makefile — modificato: `PHP := php8.2`, `CAKE := $(PHP) bin/cake.php` (fix pdo_mysql e shell-vs-PHP)
- src/Application.php — modificato: rimosso `use App\Middleware\HostHeaderMiddleware` e relativa riga `->add()` (file in src/src/ fuori autoload, non in Allowed Paths)
- src/Controller/AppController.php — modificato: rimosso `$this->loadComponent('RequestHandler')` (componente eliminato in CakePHP 5)

### Commands run
```
php -l src/Application.php          → PASS — No syntax errors detected
php -l src/Controller/AppController.php → PASS — No syntax errors detected
php -l src/Controller/DashboardController.php → PASS — No syntax errors detected
php -l src/Controller/HealthController.php → PASS — No syntax errors detected
php -l src/Controller/Api/MetricsController.php → PASS — No syntax errors detected
php -l src/Model/Table/MetricsTable.php → PASS — No syntax errors detected
php -l src/Model/Table/AlertsTable.php  → PASS — No syntax errors detected
make install                         → PASS — Nothing to install; exit 0
make migrate                         → PASS — All Done. Took 0.04s
bin/cake.php migrations status       → PASS — up 20260402000001 CreateMetricsTable / up 20260402000002 CreateAlertsTable
curl http://localhost:8080/          → PASS — HTTP 200
```

### Assunzioni fatte
- [A1] MySQL 8.0 è up e raggiungibile su 127.0.0.1 con credenziali sdi/secret (verificato: mysqladmin ping OK)
- [A2] php8.2 (sistema) usato al posto del php default (/home/codespace/.php/current/bin/php 8.3.14) perché il PHP custom manca di pdo_mysql compilato correttamente
- [A3] bin/cake è uno shell script che non accetta `php bin/cake`; il corrispettivo PHP è bin/cake.php; Makefile aggiornato di conseguenza
- [A4] HostHeaderMiddleware è in src/src/Middleware/ (path legacy, Forbidden Paths); il namespace App\Middleware non ha file in src/Middleware/ (fuori Allowed Paths); rimosso il riferimento da Application.php come fix minimale
- [A5] RequestHandlerComponent rimosso in CakePHP 5 (confermato: non presente in vendor/cakephp/cakephp/src/Controller/Component/)

### Rischi / TODO residui
- [P1] src/Controller/Api/MetricsController.php usa `$this->loadModel('Metrics')` — metodo rimosso in CakePHP 5; da correggere in TASK_m0_metric_ingestion (non blocca GET /)
- [P2] HostHeaderMiddleware rimossa: il controllo Host header è disabilitato in M0; da ripristinare in M1 creando src/Middleware/HostHeaderMiddleware.php con task dedicato
- [P3] bootstrap.php include `use Detection\MobileDetect` ma il pacchetto mobiledetect/mobiledetectlib non è in composer.json né in vendor; rilevante solo se viene invocato $request->is('mobile') — non impatta M0
- [P4] composer install non riporta errori ma usa il PHP di sistema per composer (non php8.2); funziona perché composer non richiede pdo_mysql
