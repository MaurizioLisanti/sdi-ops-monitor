# TASK_fix_makefile_php_prefix

Status: TODO
Assignee: Executor
Risk tier: LOW
BLOCKED_BY: TASK_m0_health_endpoint

## Obiettivo

`make test` fallisce con ENV_ERROR perché il Makefile invoca `./vendor/bin/phpunit` usando il binary di sistema (PHP 8.3, senza `pdo_mysql`) invece di `php8.2`. Correggere la riga di invocazione PHPUnit nel Makefile.

## Scope

- `Makefile` — riga ~38: sostituire `$(PHPUNIT)` con `$(PHP) $(PHPUNIT)` nel target `test`

## Non-scope

- Non toccare src/, config/, tests/ o coord/
- Non modificare la variabile `$(PHP)` né `$(PHPUNIT)` — solo il punto di invocazione

## Allowed Paths

- `Makefile`

## DoD

- `make test` → exit 0 (tutti i test PASS)
- `coord/HANDOFF_fix_makefile_php_prefix.md` creato con `correlation_id`
