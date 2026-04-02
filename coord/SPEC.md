# SPEC — sdi-ops-monitor
<!-- SPEC_TEMPLATE_UNIFIED v1 — compilato da PROMPT_02_repo_seed_generator_v3 -->

---

## [REQUIRED] Overview

SDI Ops Monitor è una piattaforma web di monitoraggio operativo basata su CakePHP 5.
Raccoglie eventi/metriche da sistemi AWS e on-premise, le persiste in MySQL,
espone una dashboard di stato in tempo reale e genera alert quando le metriche
superano soglie configurabili. Destinato ai team Ops/SRE di SDI.

---

## [REQUIRED] Stack Tecnologico

- **Linguaggio principale**: PHP 8.2
- **Framework**: CakePHP 5
- **Database**: MySQL 8.0
- **Test framework**: PHPUnit 10
- **Dipendenze chiave**: cakephp/migrations, cakephp/bake
- **Runtime / Deploy**: AWS (EC2/ECS) + Docker locale
- **OS target**: Linux

---

## [REQUIRED] Primary Workflow

1. Sorgente esterna (AWS SNS/SQS o HTTP POST) → invia evento/metrica → ricevuto da `POST /api/metrics`
2. Controller valida payload → salva `Metric` in MySQL → risponde 201
3. Job schedulato → esamina metriche recenti contro soglie → crea `Alert` se violata
4. Operatore apre `GET /` → visualizza dashboard con metriche e alert aperti → può fare acknowledge
5. Operatore chiama `GET /health` → verifica liveness app + DB → ottiene 200 o 503

---

## [REQUIRED] MVP Acceptance Criteria (M0)

- [ ] `make install && make migrate` eseguiti senza errori su ambiente pulito
- [ ] `GET /health` → 200 `{"status":"ok"}` in ≤ 500ms
- [ ] `POST /api/metrics` con payload JSON valido → 201 e record in DB
- [ ] `GET /` (dashboard) → 200 con contenuto HTML
- [ ] `make test` → tutti i test PASS (≥ 2 smoke test)

---

## [REQUIRED] Non-Goals (scope esplicito)

- NON farà: UI di configurazione soglie alert in M0 (rinviato a M1)
- NON farà: autenticazione/autorizzazione utenti in M0 (rinviato a M1)
- NON farà: integrazione diretta AWS Lambda (solo SNS/SQS HTTP endpoint)
- NON farà: retention policy / archivio storico metriche oltre 90gg in M0
- NON farà: notifiche push/email in M0 — solo record in DB

---

## [OPT] Target Users

- **Primary**: SRE / Ops Engineer — uso quotidiano — livello tecnico alto
- **Secondary**: Engineering Manager — uso settimanale — livello tecnico medio

---

## [OPT] Inputs

- **Sources**: HTTP POST (payload JSON), AWS SNS HTTP notification, AWS SQS poll
- **Formats**: JSON
- **Example**:
```json
{
  "source": "aws-ec2-prod-01",
  "name": "cpu_usage",
  "value": 87.4,
  "unit": "percent",
  "tags": {"env": "prod", "region": "eu-west-1"},
  "recorded_at": "2026-04-02T10:00:00Z"
}
```

---

## [OPT] Outputs

- **Destinations**: dashboard HTML, REST API JSON, alert record in DB
- **Formats**: JSON (API), HTML (dashboard)
- **Example**:
```json
{
  "data": [{"id": 1, "source": "aws-ec2-prod-01", "name": "cpu_usage", "value": 87.4}],
  "meta": {"total": 1}
}
```

---

## [OPT] Integrazioni Esterne

- AWS SNS — ingestione eventi via HTTP endpoint — limite: verifica firma SNS [DA VERIFICARE]
- AWS SQS — polling scheduled — limite: costo per polling frequency [DA VERIFICARE]

---

## [OPT] Security & PII

- **Dati PII gestiti**: NO (solo metriche infrastrutturali)
- **Confidentiality level**: interno
- **Compliance**: N/A

---

## [OPT] Observability

- **Log format**: JSON strutturato SÌ
- **correlation_id**: SÌ (da implementare in M1)
- **Audit trail**: SÌ (alert status changes)
- **Metrics chiave**: latenza POST /api/metrics p95, alert open count, error rate

---

## [OPT] Performance / Cost Budget

- **Latency p95**: < 300ms per `POST /api/metrics`
- **Volume atteso**: ~5.000 eventi/giorno in M0
- **Costo per chiamata stimato**: [DA VERIFICARE — dipende da SQS polling interval]

---

## [OPT] Architettura

```
[AWS SNS/SQS]
      │
      ▼
[POST /api/metrics]  ──►  MetricsController  ──►  MetricsTable  ──►  MySQL
                                                        │
                                                   Alert Engine (M1)
                                                        │
                                                   AlertsTable  ──►  MySQL
                                                        │
                                                   Dashboard GET /
```

---

## [OPT] Milestones

- **M0** (demoabile): app boots, health check, metric ingestion, dashboard 200, test PASS
- **M1** (usable): alert engine, AWS SNS signature validation, correlation_id, auth
- **M2** (prod-lite): SQS polling job, alert notifications, retention policy, deploy su AWS ECS

---

## [OPT] Risks / Unknowns

- R1 [P:M / I:A]: SNS signature validation non implementata → metriche false iniettabili → Mitigazione: HMAC check in M1
- R2 [P:B / I:M]: MySQL connection pool esaurito sotto burst → Mitigazione: connection limit config + circuit breaker M1

---

## Assunzioni

- [A1] Il DB MySQL 8.0 è disponibile e raggiungibile all'avvio — usato in migration e tests
- [A2] AWS credentials sono iniettate via env vars — non hardcoded nel codice
- [A3] In M0 non c'è autenticazione — accesso libero all'API e dashboard

---

## Note per l'Agente

- Non modificare il nome della tabella `metrics` — referenziato dalle migrations
- CakePHP 5 usa namespace `App\` — non usare namespace legacy `App` senza backslash
- I TODO (Planner) nei file skeleton sono intenzionali — non rimuoverli prima del Planner pass

---

## [REQUIRED] Obiettivo Modifiche

Costruire da zero la piattaforma sdi-ops-monitor partendo dallo scaffold M0.

---

## [REQUIRED] Vincoli

- PHP ≥ 8.2 obbligatorio (typed properties, match expressions)
- CakePHP 5 — non retro-compatibile con CakePHP 4
- Nessuna business logic nel seed — solo contratti e skeleton

---
<!-- Fine SPEC_TEMPLATE_UNIFIED v1 — sdi-ops-monitor -->
