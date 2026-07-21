# AGENTS.md

Conventions for AI coding agents (and humans) working in this repository.

## Project

Operational monitoring for Italian e-invoicing (SDI / FatturaPA) flows.
PHP 8.2 · CakePHP 5 · MySQL 8.0 · AWS (SQS, SNS, CloudWatch, Elastic Beanstalk).

## Commands

| Task | Command |
|---|---|
| Install dependencies | `make install` |
| Run migrations | `make migrate` |
| Run test suite | `make test` |
| Static analysis | `vendor/bin/phpstan analyse` |
| Code style check | `make phpcs` |
| Auto-fix code style | `make phpcbf` |
| Start dev server | `make up` |

## Definition of Done

A change is done only when **all** of the following pass — the same gates
enforced by `.github/workflows/ci.yml`:

1. `make test` — PHPUnit suite
2. `vendor/bin/phpstan analyse` — level 5, blocking
3. `make phpcs` — CakePHP coding standard, blocking

None of these gates is optional or advisory. A failing gate blocks the merge.

## Conventions

- `declare(strict_types=1);` at the top of every PHP file.
- Business logic lives in `src/Service/`, not in controllers.
- Every class and public method carries a docblock explaining *why*, not *what*.
- Configuration is read through `env()` — never hardcode credentials, endpoints
  or account identifiers.
- Logging is structured JSON and always carries the request `correlation_id`
  (see `src/Middleware/CorrelationIdMiddleware.php`).
- Error handling fails closed: on missing configuration or an unverifiable
  input, deny rather than degrade silently.

## Sensitive paths — human review required

Changes touching these require explicit human review before merge, regardless
of whether automated gates pass:

- `src/Middleware/BasicAuthMiddleware.php` — authentication
- `src/Service/SnsSignatureValidator.php` — signature verification, SSRF surface
- `src/Controller/LogViewerController.php` — log exposure, path traversal surface
- `config/` — configuration and credential resolution

## Never

- Commit secrets, tokens or real fiscal data. `.env` is ignored; keep it that way.
- Log invoice content, VAT numbers or any other PII.
- Weaken or skip a quality gate to make a change merge.

## Methodology

This project was built with a governed multi-agent pipeline
(planner → executor → reviewer), documented in
**[agentic-dev-pipeline](https://github.com/MaurizioLisanti/agentic-dev-pipeline)**.
