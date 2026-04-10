---
task_id: TASK_m3_phpcs
created: 2026-04-10T18:00:00Z
updated: 2026-04-10T18:00:00Z
milestone: M3
assignee: Executor
suggested_agent: Claude
status: BLOCKED
risk_tier: LOW
correlation_id_template: "UUID-v4 generato al momento della creazione HANDOFF"
---

# TASK_m3_phpcs — PHPCS Code Style Check (PSR-12)

## Obiettivo

Integrare PHP_CodeSniffer con ruleset PSR-12 nel progetto.
Aggiungere target `make phpcs` per esecuzione locale e step in
`.github/workflows/ci.yml` (già creato da TASK_m3_ci_pipeline) per
enforcement automatico su ogni PR. Nel primo run, le violation esistenti
NON causano CI failure (warning-only) — la baseline viene stabilita.

---

## Scope

- [ ] `composer require --dev squizlabs/php_codesniffer` aggiunto a `composer.json`
- [ ] `phpcs.xml`: ruleset PSR-12, include `src/`, exclude `vendor/` e
      `src/Application.php` (file generato da CakePHP con stile proprio)
- [ ] `make phpcs`: target che esegue `./vendor/bin/phpcs --standard=phpcs.xml src/`
- [ ] `make phpcbf` (opzionale): target per auto-fix con phpcbf
- [ ] Step `phpcs` aggiunto in `.github/workflows/ci.yml` dopo `make test`,
      con `--warning-severity=0` (no CI failure su warning nel primo run)

## Non-scope

- [ ] NON correggere violation PHPCS esistenti in questo task
        (chore separato, non in scope M3)
- [ ] NON modificare codice PHP applicativo
- [ ] NON aggiungere altri linter (PHPStan, Psalm, PHP-CS-Fixer) — solo PHPCS
- [ ] NON impostare CI failure su violation nel primo run (solo warning)

---

## Risk tier: LOW

- Solo tooling e configurazione — nessun cambiamento al runtime applicativo
- `composer.lock` cambia (dipendenza dev aggiunta) — non impatta prod build

---

## Allowed paths

```
phpcs.xml
composer.json
composer.lock
Makefile
.github/workflows/ci.yml
```

## Forbidden paths

```
src/
tests/
templates/
config/
```

---

## Dipendenze

- **BLOCKED_BY**: TASK_m3_ci_pipeline
  (step phpcs viene aggiunto a `ci.yml` già esistente — evita conflitto di merge)
- **BLOCKS**: N/A
- **Pre-check**: TASK_m3_ci_pipeline DONE? → **NO** (attualmente TODO) → stato: **BLOCKED**

---

## DoD

- [ ] `composer install` risolve senza conflitti con phpcs aggiunto come dev-dependency
- [ ] `make phpcs` esegue senza crash (exit 0 se zero violation, exit 1 con report se ci sono)
- [ ] `phpcs.xml` configurato: PSR-12, `<file>src/</file>`, `<exclude-pattern>*/vendor/*</exclude-pattern>`
- [ ] Step phpcs in `.github/workflows/ci.yml` con `continue-on-error: true`
        o `--warning-severity=0` (non blocca CI nel primo run)
- [ ] `coord/HANDOFF_m3_phpcs.md` creato con `correlation_id` UUID v4
- [ ] diff summary nel HANDOFF

---

## Comandi verifica

```bash
# Installa dipendenze (incluso phpcs appena aggiunto)
composer install

# Esegui PHPCS
./vendor/bin/phpcs --standard=phpcs.xml src/
# → report violation se presenti; exit 0 se zero violation

# Auto-fix (opzionale)
./vendor/bin/phpcbf --standard=phpcs.xml src/

# Via Makefile
make phpcs

# Verifica step in CI
grep -A3 "phpcs" .github/workflows/ci.yml
```

---

## Assunzioni

- [A1] `squizlabs/php_codesniffer` compatibile con PHP 8.2 e le dipendenze CakePHP 5 attuali
- [A2] Le violation PSR-12 esistenti NON causano CI failure in questo task
        — `continue-on-error: true` sullo step phpcs
- [A3] La correzione sistematica delle violation è un task chore separato, fuori scope M3
- [A4] `composer.lock` viene committato insieme a `composer.json`
