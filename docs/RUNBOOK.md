# SDI Ops Monitor — Operations Runbook
# Guida Operativa SDI Ops Monitor

**Version**: M3 (2026-04-10)
**Audience / Destinatari**: SRE, Ops Engineer, on-call responder
**Stack**: PHP 8.2 / CakePHP 5 / MySQL 8.0 / AWS SQS
**Source of truth / Fonte di verità**: `coord/STATE.json`, `coord/SPEC.md`

> **Quick reference / Riferimento rapido**
> ```
> make install   → install dependencies
> make migrate   → run DB migrations
> make up        → start server on :8080
> make test      → run test suite
> make routes    → list all routes
> make clean     → clear CakePHP caches
> ```

---

## Table of Contents / Indice

1. [Quick Start](#1-quick-start)
2. [Environment Variables Reference](#2-environment-variables-reference)
3. [Dashboard & Alert Management](#3-dashboard--alert-management)
4. [Metrics Ingestion](#4-metrics-ingestion)
5. [Log Viewer](#5-log-viewer)
6. [Scenario Simulator](#6-scenario-simulator)
7. [SQS Polling](#7-sqs-polling)
8. [AI Diagnostics](#8-ai-diagnostics)
9. [Health Check & SLO](#9-health-check--slo)
10. [Troubleshooting](#10-troubleshooting)
11. [Emergency Stop](#11-emergency-stop)
12. [Escalation Path](#12-escalation-path)

---

## 1. Quick Start

*Avvio rapido per un nuovo operatore su ambiente locale o demo.*

### Prerequisites / Prerequisiti

- PHP 8.2+ with extensions: `pdo_mysql`, `mbstring`, `intl`
- Composer 2.x
- MySQL 8.0 running and accessible
- (Optional) AWS credentials for SQS polling

### Steps / Passi

```bash
# 1. Clone and install dependencies / Clona e installa le dipendenze
git clone https://github.com/MaurizioLisanti/sdi-ops-monitor.git
cd sdi-ops-monitor
make install

# 2. Configure local environment / Configura l'ambiente locale
cp config/app.php.example config/app.php          # base CakePHP config
cp config/app_local.php.example config/app_local.php
# Edit config/app_local.php: set DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME
# and APP_AUTH_USER / APP_AUTH_PASSWORD

# 3. Run database migrations / Esegui le migrazioni del database
make migrate

# 4. Start the local server / Avvia il server locale
make up
# → Server running at http://localhost:8080

# 5. Verify the application is alive / Verifica che l'applicazione sia attiva
curl http://localhost:8080/health
# → {"status":"ok"}
```

**Expected output / Output atteso**: HTTP 200 `{"status":"ok"}` in ≤ 500 ms.

If `make migrate` fails, check DB connectivity:

```bash
mysql -h 127.0.0.1 -u <DB_USERNAME> -p<DB_PASSWORD> <DB_NAME> -e "SELECT 1"
```

---

## 2. Environment Variables Reference

*Variabili d'ambiente richieste — non hardcodare mai le credenziali nel codice.*

| Variable | Required | Description / Descrizione | Example |
|---|---|---|---|
| `DB_HOST` | Yes | MySQL host | `127.0.0.1` |
| `DB_PORT` | No | MySQL port (default: 3306) | `3306` |
| `DB_USERNAME` | Yes | MySQL user | `sdi_user` |
| `DB_PASSWORD` | Yes | MySQL password | _(random)_ |
| `DB_NAME` | Yes | MySQL database name | `sdi_ops_monitor` |
| `APP_AUTH_USER` | Yes | BasicAuth username for all protected routes | `ops` |
| `APP_AUTH_PASSWORD` | Yes | BasicAuth password — use strong random value | _(random)_ |
| `SECURITY_SALT` | Yes | CakePHP security salt — never expose publicly | _(random 64-char)_ |
| `APP_FULL_BASE_URL` | No | Full base URL for link generation | `http://localhost:8080` |
| `APP_DEBUG` | No | Debug mode (default: `false` in production) | `0` |
| `AWS_SQS_QUEUE_URL` | SQS only | Full SQS queue URL | `https://sqs.eu-west-1...` |
| `AWS_REGION` | SQS only | AWS region | `eu-west-1` |
| `AWS_ACCESS_KEY_ID` | SQS/IAM | AWS key ID (omit with instance role) | _(from IAM)_ |
| `AWS_SECRET_ACCESS_KEY` | SQS/IAM | AWS secret (omit with instance role) | _(from IAM)_ |
| `OPENROUTER_API_KEY` | AI only | OpenRouter API key — if absent, fallback active | _(from OpenRouter)_ |
| `OPENROUTER_MODEL` | No | LLM model override (default: `mistralai/mistral-7b-instruct`) | `openai/gpt-4o` |

> **Security note / Nota di sicurezza**: inject all secrets via ECS task definition environment
> or EC2 instance role — never commit to git. If a secret is accidentally committed, rotate
> it immediately before any other action.

---

## 3. Dashboard & Alert Management

*La dashboard principale mostra lo stato operativo corrente e gli alert aperti.*

### Access / Accesso

```bash
# Browser / cURL (requires BasicAuth)
curl -u ops:YOUR_PASSWORD http://localhost:8080/
# → 200 HTML — main ops dashboard
```

### Severity Traffic Light / Semaforo di Severity

| Badge | Colour / Colore | Meaning / Significato | Action Required |
|---|---|---|---|
| `critical` | 🔴 Red | Threshold ≥ 95 % (CPU/Memory) or hard breach | Page on-call immediately |
| `high` | 🟡 Yellow | Threshold ≥ 80 % CPU or ≥ 85 % Memory | Investigate within 15 min |
| `warning` | 🟠 Orange | Threshold elevated — monitor trend | Log and watch |
| `ok` / no alerts | 🟢 Green | All metrics within normal range | No action needed |

### Reading an Alert / Come leggere un alert

Each alert card shows:
- **Source** (`sdi-batch-milano-01`, `fatturapa-validator-roma-01`, …) — the originating system
- **Metric** (`cpu_usage`, `memory_usage`, `error_rate`) — the breached metric
- **Value** — the measured value at breach time
- **Severity** — `critical` / `high` / `warning`
- **Status** — `open` / `acknowledged`

### Acknowledging an Alert / Acknowledge di un alert

Navigate to the dashboard, locate the alert card, and click **Acknowledge**.
The alert status changes to `acknowledged` and the severity badge turns grey.
Acknowledged alerts are still visible but no longer trigger dashboard red state.

---

## 4. Metrics Ingestion

*Ingestione metriche via HTTP POST — usato da sistemi SDI/FatturaPA, SNS, SQS.*

### Endpoint

```
POST /api/metrics
Content-Type: application/json
Authorization: Basic <base64(user:password)>
```

### Request Payload / Payload di richiesta

```bash
curl -u ops:YOUR_PASSWORD \
     -X POST http://localhost:8080/api/metrics \
     -H "Content-Type: application/json" \
     -d '{
       "source":      "sdi-batch-milano-01",
       "name":        "cpu_usage",
       "value":       87.4,
       "unit":        "percent",
       "tags":        {"env": "prod", "region": "eu-west-1", "site": "CED Milano"},
       "recorded_at": "2026-04-10T10:00:00Z"
     }'
# → HTTP 201 {"data": {"id": 42, ...}, "meta": {...}}
```

### Field Reference / Riferimento campi

| Field | Type | Required | Notes |
|---|---|---|---|
| `source` | string | Yes | Identifier of the originating system |
| `name` | string | Yes | Metric name: `cpu_usage`, `memory_usage`, `error_rate` |
| `value` | float | Yes | Numeric measurement value |
| `unit` | string | No | `percent`, `ms`, `count`, etc. |
| `tags` | object | No | Key-value metadata (env, region, site) |
| `recorded_at` | ISO8601 | No | Defaults to server time if absent |

### Alert Thresholds / Soglie di alert

| Metric | `high` | `critical` |
|---|---|---|
| `cpu_usage` | ≥ 80 % | ≥ 95 % |
| `memory_usage` | ≥ 85 % | ≥ 95 % |
| `error_rate` | ≥ 5 % | ≥ 10 % |

Thresholds are evaluated automatically after every successful metric save.

### SDI/FatturaPA Source Identifiers

Standard source naming convention:
```
sdi-batch-<city>-<N>          e.g. sdi-batch-milano-01
fatturapa-validator-<city>-<N> e.g. fatturapa-validator-roma-01
sdi-gateway-<city>-<N>         e.g. sdi-gateway-torino-01
```

---

## 5. Log Viewer

*Visualizzatore log strutturati JSON — accessibile via browser o curl.*

### Access / Accesso

```bash
curl -u ops:YOUR_PASSWORD "http://localhost:8080/logs"
# → 200 HTML — log viewer page showing last 200 lines
```

### Query Parameters / Parametri query

| Parameter | Type | Description |
|---|---|---|
| `?lines=N` | int | Number of tail lines to display (default: 200, max: 1000) |
| `?level=<string>` | string | Filter by level: `debug`, `info`, `warning`, `error`, `critical` |
| `?correlation_id=<uuid>` | UUID | Filter by exact correlation ID — trace a single request end-to-end |

```bash
# Show last 50 error/critical entries
curl -u ops:YOUR_PASSWORD "http://localhost:8080/logs?lines=50&level=error"

# Trace a specific request by correlation ID
curl -u ops:YOUR_PASSWORD \
  "http://localhost:8080/logs?correlation_id=f3a8d2c1-5e7b-4f9a-a0b1-c2d3e4f5a6b7"
```

### Log Entry Fields / Campi di una entry

```json
{
  "timestamp":      "2026-04-10T10:00:00+00:00",
  "level":          "info",
  "correlation_id": "f3a8d2c1-5e7b-4f9a-a0b1-c2d3e4f5a6b7",
  "message":        "Metric saved successfully.",
  "context":        {"source": "sdi-batch-milano-01", "name": "cpu_usage", "value": 87.4}
}
```

**Log files on disk / File di log su disco**:
- `logs/app.log` — info, debug, notice
- `logs/error.log` — warning, error, critical

---

## 6. Scenario Simulator

*Simulatore di scenari SDI/FatturaPA — tool di test e demo senza traffico reale.*

### Access / Accesso

```bash
# Open the scenario selection form
curl -u ops:YOUR_PASSWORD http://localhost:8080/simulate
# → 200 HTML — scenario selector with 4 predefined scenarios
```

### Available Scenarios / Scenari disponibili

| ID | Name | Source | Expected Alerts |
|---|---|---|---|
| `scenario-1` | CPU Spike — SDI Batch Processing | `sdi-batch-milano-01` | 2 (1 high + 1 critical) |
| `scenario-2` | Memory Pressure — FatturaPA Validation | `fatturapa-validator-roma-01` | 1 (1 high) |
| `scenario-3` | Normal Operation — All Clear | `sdi-gateway-torino-01` | 0 — system green |
| `scenario-4` | FatturaPA Batch Failure Spike — Naples | `sdi-batch-napoli-01` | 3 (1 high + 2 critical) |

### Running a Scenario / Eseguire uno scenario

**Via browser**: select scenario, choose Live or Dry-run, click **Run Scenario**.

**Via curl (dry-run — no DB writes)**:

```bash
curl -u ops:YOUR_PASSWORD \
     -X POST http://localhost:8080/simulate/run \
     -H "Content-Type: application/x-www-form-urlencoded" \
     -d "scenario_id=scenario-1&dry_run=1"
# → 200 HTML — results page with [DRY-RUN] log entries, Correlation ID, 0 DB writes
```

**Via curl (live — writes metrics and creates alerts)**:

```bash
curl -u ops:YOUR_PASSWORD \
     -X POST http://localhost:8080/simulate/run \
     -H "Content-Type: application/x-www-form-urlencoded" \
     -d "scenario_id=scenario-3"
# → 200 HTML — results page with [OK] log entries and Correlation ID
# scenario-3 produces 0 alerts — safe for demo without cleanup
```

### Dry-run vs Live

| Mode | DB writes | Alerts created | Use when |
|---|---|---|---|
| **Dry-run** (`dry_run=1`) | No | No | Demo, testing, verifying scenario output |
| **Live** | Yes | Yes | Full pipeline validation, before demo |

The **Correlation ID** shown in the results page is also searchable in the Log Viewer
(`?correlation_id=<id>`) to trace all events generated by that scenario run.

---

## 7. SQS Polling

*Polling schedulato dalla coda AWS SQS — eseguito tramite CLI CakePHP.*

### Command / Comando

```bash
# Standard run — fetch up to 10 messages, persist valid ones
php8.2 bin/cake.php sqs_poll

# Limit messages per run
php8.2 bin/cake.php sqs_poll --max-messages=5

# Dry-run — parse and validate without persisting or deleting from SQS
php8.2 bin/cake.php sqs_poll --dry-run
```

### Required Environment Variables / Variabili d'ambiente richieste

```bash
export AWS_SQS_QUEUE_URL="https://sqs.eu-west-1.amazonaws.com/<account-id>/<queue-name>"
export AWS_REGION="eu-west-1"
# If not using IAM instance/task role:
export AWS_ACCESS_KEY_ID="AKIA..."
export AWS_SECRET_ACCESS_KEY="..."
```

### Cron Configuration / Configurazione cron

```cron
# Run every 60 seconds — append to /etc/cron.d/sdi-ops-monitor
* * * * * www-data cd /var/www/html && php8.2 bin/cake.php sqs_poll >> /var/log/sqs_poll.log 2>&1
```

### Troubleshooting SQS / Risoluzione problemi SQS

| Symptom | Cause | Fix |
|---|---|---|
| `CredentialsException` | Missing AWS credentials | Export env vars or attach IAM role |
| `QueueDoesNotExist` | Wrong queue URL | Verify `AWS_SQS_QUEUE_URL` and region |
| `0 messages processed` for extended period | Queue empty or consumer offline | Check SQS console for message count |
| Metrics ingested but no alerts created | Alert thresholds not met | Check metric values vs threshold table in §4 |

---

## 8. AI Diagnostics

*Diagnosi operativa LLM-powered con fallback deterministico automatico.*

### Access / Accesso

```bash
curl -u ops:YOUR_PASSWORD http://localhost:8080/ai-diagnostics
# → 200 HTML — diagnosis card with source badge (AI / Deterministic Fallback)
```

### Setup AI Mode / Configurazione modalità AI

```bash
export OPENROUTER_API_KEY="sk-or-..."     # obtain from openrouter.ai
export OPENROUTER_MODEL="mistralai/mistral-7b-instruct"  # optional — free tier default
```

### Diagnosis Card Fields / Campi della diagnosis card

| Field | Description |
|---|---|
| **Diagnosis text** | Natural-language operational health summary (max 3 sentences) |
| **Source badge** | `AI` (blue) — LLM response / `Deterministic Fallback` (grey) — rule engine |
| **Model** | LLM model used, or `deterministic-fallback` |
| **Metrics analysed** | Count of recent metric events included in the analysis |
| **Open alerts** | Count of open alerts included in the analysis |
| **Correlation ID** | Links this diagnosis to related log entries in the Log Viewer |
| **Generated at** | UTC timestamp of the diagnosis |

### Fallback Behaviour / Comportamento del fallback

The deterministic fallback activates **automatically** when:
- `OPENROUTER_API_KEY` is not set (offline demo mode)
- The OpenRouter API returns a non-2xx response
- The API call exceeds the 5-second timeout
- The response body cannot be parsed

Fallback threshold rules (same as alert engine):

| Metric | Threshold | Fallback label |
|---|---|---|
| `cpu_usage` | ≥ 80 % | "CPU usage on \<source\>: \<value\>" |
| `memory_usage` | ≥ 85 % | "Memory usage on \<source\>: \<value\>" |
| `error_rate` | ≥ 5 % | "Error rate on \<source\>: \<value\>" |

No `OPENROUTER_API_KEY` is required for demo — the fallback is always available.

---

## 9. Health Check & SLO

*Endpoint di liveness e obiettivi di livello di servizio.*

### Health Check Endpoint

```bash
# No authentication required — safe for AWS liveness probes
curl http://localhost:8080/health
# → HTTP 200 {"status":"ok"}       — application + DB reachable
# → HTTP 503 {"status":"error",...} — DB unreachable or app fault
```

### SLO Targets / Obiettivi di SLO

| Metric | Target | Alert threshold | Measurement |
|---|---|---|---|
| `GET /health` availability | 99.9 % | < 99 % → page | External probe every 30 s |
| `POST /api/metrics` p95 latency | < 300 ms | > 500 ms → investigate | Application log `context.duration_ms` |
| `POST /api/metrics` error rate | < 0.1 % | > 1 % → page | Count 5xx / total requests per 5 min |
| `GET /ai-diagnostics` p95 latency | < 6 000 ms | > 10 000 ms → investigate | Includes 5 s OpenRouter timeout |
| Alert evaluation latency | < 500 ms after metric save | > 2 000 ms → investigate | Log Viewer `AlertsService` entries |
| SQS poll cycle duration | < 30 s per run | > 60 s → investigate | `sqs_poll.log` duration |

### When to Page / Quando paginare

Page on-call **immediately** when:
- `GET /health` returns 503 for ≥ 2 consecutive probes
- `cpu_usage` or `memory_usage` alert with severity `critical` on any SDI node
- Error rate alert on `fatturapa-validator-*` node (SDI error code 004/009 flood)
- `POST /api/metrics` error rate > 1 % sustained for > 5 minutes
- SQS poll has produced 0 messages for > 30 minutes during business hours

---

## 10. Troubleshooting

*Top problemi comuni con causa e soluzione — incluse procedure SDI/FatturaPA.*

### General Issues / Problemi generali

---

**Problem 1: HTTP 401 on all protected routes**
*Problema: 401 su tutte le route protette*

```
Cause:  APP_AUTH_USER or APP_AUTH_PASSWORD env vars not set or empty.
Fix:    export APP_AUTH_USER=ops && export APP_AUTH_PASSWORD=<password>
        Verify: curl -u ops:YOUR_PASSWORD http://localhost:8080/ → 200
```

---

**Problem 2: `make migrate` fails — "connection refused"**
*Problema: make migrate fallisce con "connection refused"*

```
Cause:  MySQL not running, or wrong DB_HOST/DB_PORT.
Fix:    1. Verify MySQL: mysqladmin -h 127.0.0.1 -u root -p ping
        2. Check config/app_local.php values for DB_HOST, DB_PORT, DB_USERNAME.
        3. Create DB if missing:
           mysql -u root -p -e "CREATE DATABASE sdi_ops_monitor;"
           mysql -u root -p -e "GRANT ALL ON sdi_ops_monitor.* TO 'sdi_user'@'%';"
        4. Re-run: make migrate
```

---

**Problem 3: POST /api/metrics returns 422 Unprocessable Entity**
*Problema: POST /api/metrics risponde 422*

```
Cause:  Missing required fields (source, name, value) or invalid value type.
Fix:    Check request payload — ensure "value" is a float, "source" and "name" are non-empty.
        Valid example:
        {"source":"sdi-batch-milano-01","name":"cpu_usage","value":87.4,"unit":"percent"}
```

---

**Problem 4: AI Diagnostics shows "Deterministic Fallback" badge unexpectedly**
*Problema: AI Diagnostics mostra sempre il badge "Deterministic Fallback"*

```
Cause:  OPENROUTER_API_KEY not set, expired, or OpenRouter API unreachable.
Fix:    1. Verify: echo $OPENROUTER_API_KEY (must be non-empty)
        2. Test connectivity: curl -s https://openrouter.ai/api/v1/models -H "Authorization: Bearer $OPENROUTER_API_KEY"
        3. If quota exceeded, rotate key on openrouter.ai dashboard.
        4. If API is down, fallback is intentional — system still provides correct diagnosis.
```

---

**Problem 5: Log Viewer shows "No log file found"**
*Problema: Log Viewer mostra "No log file found"*

```
Cause:  logs/app.log does not exist yet (normal on fresh install before any request).
Fix:    1. Make at least one request to generate a log entry:
           curl -u ops:YOUR_PASSWORD http://localhost:8080/
        2. Verify file: ls -lh logs/app.log
        3. Check directory permissions: chmod 755 logs/
        If the file path is wrong, LOG_FILE_PATH in LogViewerController is hardcoded
        to ROOT/logs/app.log — verify ROOT constant matches your webroot.
```

---

### SDI/FatturaPA Specific Troubleshooting
*Risoluzione problemi specifici per il sistema SDI/FatturaPA*

---

**SDI-1: Spike di error_rate su `fatturapa-validator-*` (SDI error code 004 — certificato non valido)**
*Error code 004: file rejected — invalid or expired signing certificate*

```
Symptom: error_rate alert "high" or "critical" on fatturapa-validator-roma-01 (or similar).
         Log Viewer shows repeated 004 errors in context.tags.sdi_error.
Cause:   Batch of FatturaPA invoices submitted with expired/revoked signing certificates.
Fix:
  1. Check Log Viewer: filter by level=warning, look for sdi_error=004 in context.
  2. Identify the source system submitting invalid certificates (context.source).
  3. Notify the submitting organization to renew their qualified electronic signature (QES).
  4. Acknowledge the alert on the dashboard once the certificate batch is resolved.
  5. Monitor error_rate on the affected validator — should drop to < 5% within 15 min.
Escalation: If error_rate stays > 10% for > 30 min, escalate to SDI Integration team.
```

---

**SDI-2: Spike di `cpu_usage` su `sdi-batch-*` durante picco di ingestione (SDI error code 003)**
*Error code 003: file received — peak ingestion load*

```
Symptom: cpu_usage alert "critical" (≥ 95%) on sdi-batch-milano-01 during business hours.
         Occurs typically 09:00–11:00 and 14:00–17:00 CET (peak FatturaPA submission windows).
Cause:   High invoice volume during peak hours saturates the SDI batch processor.
Fix:
  1. Check AI Diagnostics page for an automated health assessment.
  2. Verify if the spike is transient (< 15 min) — often self-resolving after peak window.
  3. If sustained: reduce SQS poll frequency (--max-messages=3) to throttle ingestion rate.
  4. Run scenario-1 in dry-run to confirm alert thresholds are correctly calibrated.
Escalation: If cpu_usage stays > 95% for > 20 min, escalate to Infrastructure team.
```

---

**SDI-3: Duplicate invoice flood (SDI error code 009)**
*Error code 009: file rejected — duplicate invoice identifier*

```
Symptom: High error_rate + memory_usage spike on sdi-batch-napoli-01 (or similar).
         Scenario-4 ("FatturaPA Batch Failure Spike") replicates this condition.
Cause:   Re-submission of already-processed invoices (common after a downstream outage).
Fix:
  1. Run scenario-4 in dry-run to baseline expected alert count for this failure mode.
  2. Check Log Viewer: filter correlation_id of the affected SQS poll run.
  3. Identify duplicated invoice_id values in the message context.
  4. Coordinate with the originating SDI node operator to stop re-submission.
  5. Acknowledge alerts after re-submission stops. Clear acknowledged alerts from dashboard.
Escalation: If message volume > 1000/hour, activate Emergency Stop (§11) and page SDI team.
```

---

**SDI-4: SQS queue depth growing — messages not being consumed**
*La coda SQS cresce e i messaggi non vengono consumati*

```
Symptom: SQS ApproximateNumberOfMessagesVisible increasing in AWS Console.
         sqs_poll logs show 0 messages processed despite queue depth > 0.
Cause:   - Cron job not running (scheduler failure)
         - DB unreachable — SqsPollCommand::execute() throws DatabaseException
         - AWS credentials expired or IAM role detached
Fix:
  1. Check cron: crontab -l | grep sqs_poll; tail -50 /var/log/sqs_poll.log
  2. Test manual run: php8.2 bin/cake.php sqs_poll --dry-run
  3. Verify DB: make test (if test suite passes, DB is reachable)
  4. Verify AWS credentials: aws sqs get-queue-attributes --queue-url $AWS_SQS_QUEUE_URL
  5. Restart cron daemon if needed: systemctl restart cron
Escalation: If queue depth > 10,000 messages, page on-call — data loss risk.
```

---

## 11. Emergency Stop

*Procedura di stop di emergenza — da attivare solo per eventi critici.*

### When to Activate / Quando attivare

Activate Emergency Stop for any of these conditions:
- **PII_LEAK**: Personal identifiable data found in logs or API responses
- **SECRET_LEAK**: Credentials, tokens, or API keys committed to git or visible in logs
- **COST_EXPLODE**: AWS SQS/Lambda costs spike unexpectedly above monthly budget
- **DATA_CORRUPTION**: Metrics or alerts table showing signs of corrupted data
- **SECURITY_BREACH**: Unauthorized access attempt detected in logs

### Procedure / Procedura

```bash
# Step 1 — Stop the SQS poller immediately
# Kill any running sqs_poll processes
pkill -f "sqs_poll"

# Step 2 — Stop the web server if needed
# Find and stop the PHP built-in server
pkill -f "php8.2 -S"

# Step 3 — Create HALT file in coord/
cat > coord/HALT_$(date -u +%Y%m%dT%H%M%SZ).md << 'EOF'
# HALT — <PII_LEAK | SECRET_LEAK | COST_EXPLODE | OTHER>
**Date / Data**: <ISO8601>
**Trigger**: <type>
**Detected by / Rilevato da**: <agent or operator name>
**Detail / Dettaglio**: <description — DO NOT include the sensitive data itself>
**Action taken / Azione intrapresa**: <what was stopped>
**Escalation**: <who to contact>
EOF

# Step 4 — Revoke secrets if SECRET_LEAK
# Rotate OPENROUTER_API_KEY on openrouter.ai
# Rotate APP_AUTH_PASSWORD immediately
# If AWS credentials leaked: deactivate key in IAM console immediately

# Step 5 — Notify escalation path (see §12)
```

### Replan with Complexity Manager / Replan con Complexity Manager

After resolving the emergency:
1. Update `coord/HALT_*.md` with resolution details
2. Update `coord/STATE.json` — clear `open_halts`
3. Create a `coord/TASK_fix_<slug>.md` for the remediation work
4. Re-run `make test` to verify system integrity
5. Restart services only after `make test` passes

---

## 12. Escalation Path

*Percorso di escalation per severità — da seguire nell'ordine indicato.*

### Escalation Matrix / Matrice di escalation

| Severity | Condition | First responder | Escalate to | Time limit |
|---|---|---|---|---|
| **P0 — Critical** | Health 503 × 2, `critical` alert active, SECRET/PII leak | On-call SRE | Engineering Manager | Immediately |
| **P1 — High** | `high` alert sustained > 15 min, SQS queue depth > 5,000 | On-call SRE | Infrastructure Lead | Within 15 min |
| **P2 — Medium** | `warning` alert, single SQS poll failure, AI Diagnostics timeout | Ops Engineer | Senior SRE | Within 1 hour |
| **P3 — Low** | Log file growing unexpectedly, minor UI issue | Any engineer | Ticket to backlog | Next business day |

### Contact Points / Punti di contatto

| Role | Responsibility | Contact method |
|---|---|---|
| **On-call SRE** | First responder for all P0/P1 alerts | PagerDuty rotation / Slack `#ops-alerts` |
| **Infrastructure Lead** | AWS infrastructure, SQS, IAM roles | Slack `#infra` |
| **SDI Integration Team** | SDI error codes, FatturaPA certificate issues | Email `sdi-integration@<company>` |
| **Engineering Manager** | Budget overrun, security incidents, replan decisions | Direct call / Slack DM |
| **Complexity Manager (Agent)** | Pipeline replan, wave restructuring | `coord/STATE.json` + new TASK file |

### SDI/FatturaPA Escalation / Escalation SDI specifico

For SDI-specific incidents involving Italian Revenue Agency (Agenzia delle Entrate) interfaces:

1. **Identify** the SDI error code in the Log Viewer (`context.tags.sdi_error`)
2. **Classify**:
   - Error `003` (file received) — ingestion load issue → Infrastructure Lead
   - Error `004` (invalid certificate) → SDI Integration Team + affected organization
   - Error `009` (duplicate invoice) → SDI Integration Team
3. **Document** in the incident channel with: source system, error code, volume, correlation IDs
4. **Do not** attempt to reprocess rejected invoices without SDI Integration Team approval

---

*End of Runbook / Fine Runbook*
*Last updated: 2026-04-10 — sdi-ops-monitor M3*
*Maintained by: SRE team — update after every milestone (M4+)*
