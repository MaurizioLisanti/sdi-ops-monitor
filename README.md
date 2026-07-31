# sdi-ops-monitor

[![CI](https://github.com/MaurizioLisanti/sdi-ops-monitor/actions/workflows/ci.yml/badge.svg)](https://github.com/MaurizioLisanti/sdi-ops-monitor/actions/workflows/ci.yml)
[![PHP 8.2](https://img.shields.io/badge/PHP-8.2-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![CakePHP 5](https://img.shields.io/badge/CakePHP-5-D33C43?logo=cakephp&logoColor=white)](https://cakephp.org/)
[![AWS Elastic Beanstalk](https://img.shields.io/badge/AWS-Elastic%20Beanstalk-FF9900?logo=amazonaws&logoColor=white)](https://aws.amazon.com/elasticbeanstalk/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

## The problem

SDI flow failures surface only when the client calls.
By then the invoice is blocked, the deadline is missed
and the fix is urgent. There is no visibility
until something breaks.

## The solution

A real-time traffic-light dashboard that monitors
SDI/FatturaPA flows continuously — green, yellow or red
before the client calls. AI-assisted diagnostics
with deterministic fallback when no API key is available.

## Who is it for

Technical teams managing SDI/FatturaPA flows in production
who need operational visibility before failures
become client emergencies.

---

## Architecture

```
  SDI / GovWay (mTLS)
         │
         ▼
  REST API  ◄────────────────────────────────────────────────────────────────────────────┐
  /api/metrics                                                                           │
  /api/alerts                                                                            │
         │                                                                               │
         ▼                                                                               │
  ┌──────────────┐    enqueue     ┌─────────────┐   persist   ┌────────────────────┐    │
  │  AWS SQS     │ ◄───────────── │  PHP App    │ ──────────► │  RDS MySQL 8.0     │    │
  │  Queue       │                │  (EB)       │             │  metrics / alerts  │    │
  └──────────────┘                └─────┬───────┘             └────────────────────┘    │
         │                             │                                                │
         │  bin/cake sqs:poll          │  read                                          │
         ▼                             ▼                                                │
  ┌──────────────┐          ┌──────────────────────┐                                    │
  │  SQS Worker  │          │  Dashboard (semaforo) │                                   │
  │  Command     │          │  AI Diagnostics       │ ──► OpenRouter AI                 │
  └──────────────┘          │  Log Viewer           │     (with det. fallback)          │
                            │  Scenario Simulator   │ ──────────────────────────────────┘
                            └──────────────────────┘
                                      │
                                      ▼
                             CloudWatch Logs / Metrics
```

---

## Tech Stack

| Layer | Technology |
|---|---|
| Language | PHP 8.2 |
| Framework | CakePHP 5 |
| Database | MySQL 8.0 / AWS RDS |
| Infrastructure | AWS Elastic Beanstalk (Amazon Linux 2023) |
| Queue | AWS SQS |
| Observability | AWS CloudWatch Logs & Metrics |
| AI Diagnostics | OpenRouter API (deterministic fallback when key absent) |
| Frontend | Bootstrap 5 |
| CI | GitHub Actions |

---

## Features

- **Traffic-light dashboard** — aggregate metric status displayed as green / yellow / red with per-flow breakdown
- **REST metric ingestion** — `POST /api/metrics` accepts JSON payloads from SDI/GovWay via mTLS
- **Automatic alert engine** — threshold-based rules evaluated on every ingestion; alerts persisted to RDS
- **Asynchronous SQS worker** — `bin/cake sqs:poll` dequeues and processes messages without blocking the web tier
- **Domain metric model**  — receipt lag, rejection rate, certificate expiry, with infrastructure kept as diagnostic context rather than as the signal
- AI diagnostics that name a cause and an action — a lag without rejections is a stalled channel, a rejection spike with an expiring certificate is code `00100`; deterministic fallback works without an API key.
- **SDI scenario simulator** — six scenarios, one per failure mode: stalled channel, saturation-induced lag, expired certificate (`00100`), duplicate file names (`00002`), payload rejection, and certificate expiry warning
- **Countdown alert thresholds** — rules fire on values falling as well as rising, so certificate expiry warns days before the outage instead of reporting it afterwards
- **Structured JSON log viewer** — paginated, filterable view of application logs
- **Health check endpoint** — `GET /health` returns `{"status":"ok"}` for load-balancer probes
- **Bilingual runbook (EN/IT)** — operational procedures documented in `docs/RUNBOOK.md`

---

## Quick Start (local)

```bash
# 1. Clone
git clone https://github.com/MaurizioLisanti/sdi-ops-monitor.git
cd sdi-ops-monitor

# 2. Install PHP dependencies
composer install

# 3. Create local config (edit DB credentials inside)
cp config/app_local.php.example config/app_local.php

# 4. (Optional) copy base config if not present
[ -f config/app.php ] || cp config/app.php.example config/app.php

# 5. Run DB migrations
bin/cake migrations migrate

# 6. Start the development server
bin/cake server
# → http://localhost:8765
```

Required environment variables (or set them in `config/app_local.php`):

| Variable | Description |
|---|---|
| `DB_HOST` | MySQL host |
| `DB_USERNAME` | MySQL user |
| `DB_PASSWORD` | MySQL password |
| `DB_NAME` | Database name |
| `SECURITY_SALT` | CakePHP security salt (any long random string) |
| `APP_AUTH_USER` | Basic-auth username for protected routes |
| `APP_AUTH_PASSWORD` | Basic-auth password for protected routes |
| `OPENROUTER_API_KEY` | *(optional)* enables AI diagnostics |

---

## What it demonstrates

- Production-grade PHP 8.2 / CakePHP 5 on AWS Elastic Beanstalk
- Async architecture with SQS worker decoupled from web tier
- Domain metric model — receipt lag, rejection rate, certificate expiry, with infrastructure kept as diagnostic context rather than as the signal
- AI diagnostics that name a cause and an action — a lag without rejections is a stalled channel, a rejection spike with an expiring certificate is code `00100`; deterministic fallback works without an API key
- mTLS integration with GovWay toward institutional SDI endpoints
- PHPUnit test suite + CakePHP coding standard, both enforced in CI

## Production status

Production-grade architecture deployed on AWS Elastic Beanstalk:
SQS async workers, RDS MySQL, CloudWatch observability, CI on every push.

SDI/FatturaPA integration is exercised through a scenario simulator that
replays official SDI rejection codes (00002, 00100) as published by the
Agenzia delle Entrate — no real invoice data is transmitted or stored.

Part of a broader ecosystem: fatturapa-mcp-server → sdi-ops-monitor.

---

## How this was built

This project was not prompted into existence in one shot. It was built with a
governed multi-agent pipeline — planner, executor and reviewer as separate
bounded roles, each with declared authority, allowed paths and stop conditions.
No task reached `main` without passing static analysis, the test suite and a
review gate.

| Gate | Enforced by | Blocking |
|---|---|---|
| Static analysis | PHPStan level 5 | yes |
| Coding standard | PHPCS (CakePHP ruleset) | yes |
| Test suite | PHPUnit | yes |
| Review | Reviewer agent + human sign-off | yes |

Every task produced a structured handoff: files changed with a reason per file,
commands run with their PASS/FAIL output, assumptions stated and verified,
residual risks declared, and a correlation ID linking task, logs and review.

The methodology started in this repository and was later extracted into its own
reusable project — which is what the `Delete AGENTS.md` and `Delete coord/`
commits in this history are: an extraction, not a cleanup. See
**[agentic-dev-pipeline](https://github.com/MaurizioLisanti/agentic-dev-pipeline)**
for the full workflow, the seven stage prompts and the gate definitions.

Repository conventions and the Definition of Done live in
[`AGENTS.md`](AGENTS.md).

## Deploy to AWS

> Full guide: [`docs/aws_deploy.md`](docs/aws_deploy.md)

```bash
# Initialise EB project (first time)
eb init sdi-ops-monitor --platform "PHP 8.2 running on 64bit Amazon Linux 2023" --region eu-west-1

# Create environment
eb create sdi-ops-monitor-prod

# Deploy
eb deploy
```

Environment variables and secrets are injected via the EB console or `eb setenv`.  
SSL termination, SQS polling and CloudWatch agent are configured via `.ebextensions/`.

---

## Tests

```bash
# Unit + integration test suite (86 tests)
vendor/bin/phpunit

# Code-style check (CakePHP standard)
vendor/bin/phpcs --standard=CakePHP src/
```

Or use the Makefile shortcuts:

```bash
make test
make phpcs
```

---

## Directory Structure

```
sdi-ops-monitor/
├── .ebextensions/          # AWS EB configuration (PHP, Apache, SQS, SSL…)
├── .github/workflows/      # GitHub Actions CI pipeline
├── config/
│   ├── Migrations/         # Phinx DB migrations
│   ├── app.php             # Base CakePHP config
│   └── routes.php          # Application routes
├── docs/                   # Runbook, AWS deploy guide, SQS worker, GovWay mTLS
├── src/
│   ├── Command/            # bin/cake commands (SqsPollCommand)
│   ├── Controller/         # Dashboard, AI diagnostics, API, health, log viewer
│   ├── Middleware/         # BasicAuth, CorrelationId
│   ├── Model/              # Entities + Tables (Metric, Alert)
│   └── Service/            # AlertsService, AiDiagnosticsService, SqsPollerService…
├── templates/              # CakePHP view templates (Bootstrap 5)
├── tests/TestCase/         # PHPUnit test suite (mirrors src/ structure)
└── webroot/                # Public document root
```

---

## FatturaPA / SDI Domain

| Term | Description |
|---|---|
| **SDI** | *Sistema di Interscambio* — Italy's government hub for electronic B2B/B2G invoices |
| **FatturaPA** | XML invoice format mandated for all Italian VAT-registered entities |
| **GovWay** | Open-source API gateway used for mTLS termination toward SDI endpoints |
| **mTLS** | Mutual TLS — both client and server present certificates; required by SDI |

**Simulated SDI rejection codes**

SDI rejection codes are five digits and are returned inside a *Notifica di
Scarto* (NS). The codes below are taken from the official control list
published by the Agenzia delle Entrate
([Elenco dei controlli, v1.7](https://www.fatturapa.gov.it/export/documenti/Elenco-Controlli-versione-1.7.pdf)).

| Code | Meaning | Simulated by |
|---|---|---|
| `00100` | Certificato di firma scaduto — the signing certificate has expired | `ScenarioService::run('scenario-4')` |
| `00002` | Nome file duplicato — a file with this name was already transmitted | `ScenarioService::run('scenario-5')` |

Note that `00002` is about the file name, not the invoice number: a rejected
invoice may be re-sent keeping its number, but the file must be renamed.
Missing this is the usual reason a corrected batch is refused a second time.

A successful transmission produces no rejection code — it produces a *Ricevuta
di Consegna* (RC). Scenarios `1`, `2`, `3` and `6` therefore carry no code:
they model stalls and warnings, where files are accepted and it is the
answers that are missing.

---

## Related projects

- **[fatturapa-mcp-server](https://github.com/MaurizioLisanti/fatturapa-mcp-server)** —
  MCP server exposing FatturaPA/SDI tooling to any AI agent: XML validation,
  invoice data extraction, SDI error-code lookup, VAT number verification.
  Use together for end-to-end AI visibility into Italian e-invoicing operations.

- **[agentic-dev-pipeline](https://github.com/MaurizioLisanti/agentic-dev-pipeline)** —
  The governed multi-agent development pipeline this project was built with:
  risk-based routing, hard quality gates, auditable handoffs.

## License

[MIT](LICENSE)
