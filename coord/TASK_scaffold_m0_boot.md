# TASK_scaffold_m0_boot — CakePHP 5 boot + migrazioni

**Status**: TODO  
**Wave**: 1 · Milestone: M0  
**Risk tier**: MED  
**Dipendenze**: nessuna (first task)

---

## Scope

Rendere l'applicazione CakePHP 5 avviabile con `make install && make up && make migrate`
su ambiente pulito. Tutti i file skeleton devono compilare senza errori PHP.

## Deliverable

- `composer install` senza errori
- `make migrate` applica le due migration senza errori su DB MySQL 8.0
- `GET /` risponde (anche solo 500 con stack trace è accettabile — l'importante è che il routing parta)
- Nessun syntax error in src/ verificato con `php -l`

## DoD (Definition of Done)

```bash
make install
make migrate
php -l src/Application.php
php -l src/Controller/AppController.php
php -l src/Controller/DashboardController.php
php -l src/Controller/HealthController.php
php -l src/Controller/Api/MetricsController.php
# → No syntax errors detected
```

## Note

- [A1] DB MySQL deve essere up prima di `make migrate`
- config/app_local.php deve essere copiato da app_local.php.example e configurato
- TODO (Planner): verificare se serve `config/bootstrap.php` separato per CakePHP 5
