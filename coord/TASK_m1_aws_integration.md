# TASK_m1_aws_integration — AWS SNS signature validation

<!-- [UPDATED: 2026-04-03 — Planner pass: ex-skeleton BLOCKED, ora TODO con campi completi] -->

---

## Metadata

```
created:   2026-04-02T00:00:00Z
updated:   2026-04-03T16:00:00Z
assignee:  Executor
status:    TODO
wave:      2
milestone: M1
risk_tier: HIGH
```

---

## Obiettivo

Implementare `SnsSignatureValidator` che verifica la firma HMAC/RSA delle
notifiche HTTP di AWS SNS prima che `MetricsController` le elabori, impedendo
l'iniezione di metriche false. Gestire il flusso `SubscriptionConfirmation`
senza creare metriche.

---

## Scope

- [x] `src/Service/SnsSignatureValidator.php` — nuovo:
      `validate(array $headers, string $rawBody): bool`
      - Controlla `X-Amz-Sns-Message-Type` header
      - Scarica il certificato da `SigningCertURL` solo se dominio è
        `*.amazonaws.com` o `*.amazon.com` (whitelist obbligatoria — sicurezza)
      - Verifica firma SHA1-RSA tramite `openssl_verify()`
      - Restituisce `true` se firma valida, `false` altrimenti
      `handleSubscriptionConfirmation(array $payload): void`
      - Esegue GET su `SubscribeURL` per confermare la sottoscrizione SNS
      - Ritorna senza creare Metric entities
- [x] `src/Controller/Api/MetricsController.php` — modifica:
      se `X-Amz-Sns-Message-Type` header presente → pipeline SNS:
        1. Deserializza body JSON
        2. Se tipo `SubscriptionConfirmation` → `handleSubscriptionConfirmation()` → 200
        3. Se tipo `Notification` → `validate()` → se FAIL → 400; se PASS → elabora metrica
      se header assente → pipeline normale (invariata)
- [x] `tests/TestCase/Service/SnsSignatureValidatorTest.php` — nuovo:
      `testValidSignatureReturnsTrue` (fixture con certificato e firma noti),
      `testInvalidSignatureReturnsFalse`,
      `testRejectsCertFromNonAmazonDomain`

## Non-scope

- [ ] NON implementare AWS SQS polling (M2)
- [ ] NON verificare firma per MessageType != Notification|SubscriptionConfirmation
- [ ] NON implementare SNS topic management o provisioning
- [ ] NON memorizzare certificati SNS in DB (cache in-memory o filesystem tmp/)

---

## Risk tier

**HIGH** — modifica il path critico di ingestione metriche; un bug può bloccare
tutti gli ingest AWS SNS. Il validatore deve fallire in modo sicuro (fail-closed):
firma invalida → 400, nessun dato scritto in DB.

---

## Allowed paths

```
src/Service/SnsSignatureValidator.php
src/Controller/Api/MetricsController.php
tests/TestCase/Service/SnsSignatureValidatorTest.php
tests/TestCase/Controller/MetricsControllerTest.php
```

## Forbidden paths

```
src/Application.php               # NON toccare — gestito da observability/auth
config/Migrations/                # NON modificare schema
src/Model/                        # NON toccare Model/Table
coord/                            # solo Planner/Reviewer
```

---

## Dipendenze

```
BLOCKED_BY: TASK_m1_alert_engine
BLOCKS:     TASK_m1_tests_m1

Pre-check:  TASK_m1_alert_engine DONE? NO → BLOCKED
                                        SÌ → pronto per assegnazione

Nota: MetricsController.php è condiviso con TASK_m1_alert_engine —
      esecuzione seriale obbligatoria.
```

---

## DoD

```bash
# SNS Notification con firma invalida → 400
curl -s -w "\nHTTP:%{http_code}" -X POST http://localhost:8080/api/metrics.json \
  -H "X-Amz-Sns-Message-Type: Notification" \
  -H "Content-Type: application/json" \
  -d '{"Type":"Notification","Message":"{}","Signature":"INVALID=="}'
# → HTTP:400

# Request normale (senza header SNS) → 201 invariato
curl -s -w "\nHTTP:%{http_code}" -X POST http://localhost:8080/api/metrics.json \
  -H "Content-Type: application/json" \
  -d '{"source":"test","name":"cpu_usage","value":50,"unit":"percent","recorded_at":"2026-04-03T10:00:00Z"}'
# → HTTP:201

# Suite completa PASS
make test
# → OK (N tests, N assertions) — exit 0
```

**Criteri DONE:**
- [ ] SNS Notification con firma invalida → 400 (nessuna metrica creata)
- [ ] SNS SubscriptionConfirmation → 200 (nessuna metrica creata)
- [ ] Request normale senza header SNS → pipeline invariata (201 o 422)
- [ ] `SigningCertURL` di dominio non-Amazon → 400 immediato (no HTTP fetch)
- [ ] `SnsSignatureValidatorTest` → 3 test PASS (con certificato/firma mockati)
- [ ] `make test` → exit 0
- [ ] `coord/HANDOFF_m1_aws_integration.md` creato con `correlation_id`

---

## Comandi verifica (stack-specifici)

```bash
# Linting
php8.2 -l src/Service/SnsSignatureValidator.php
php8.2 -l src/Controller/Api/MetricsController.php

# Test servizio isolato
php8.2 vendor/bin/phpunit tests/TestCase/Service/SnsSignatureValidatorTest.php
# → OK (3 tests, N assertions)

# Suite completa
make test
```

---

## Assunzioni

- [A2] AWS credentials NON necessarie per questa feature (SNS invia HTTP push;
       il validatore usa solo il certificato pubblico SNS)
- [A9] PHP 8.2 runtime; `openssl_verify()` disponibile nell'estensione openssl
- [A16] Il certificato SNS viene scaricato via `file_get_contents()` con
        SSL verify abilitato; in test viene iniettato come fixture (no rete)
- [A17] SNS usa SHA1withRSA come algoritmo firma (Signature Version 1) —
        documentato in AWS SNS Developer Guide
- [A18] Il campo `Message` nelle fixture SNS di test è un JSON serializzato
        come stringa (non un oggetto nested) — comportamento AWS reale
