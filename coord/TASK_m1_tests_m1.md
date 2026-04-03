# TASK_m1_tests_m1 — PHPUnit integration suite M1

<!-- [UPDATED: 2026-04-03 — Planner pass: nuovo task M1, tutti i campi compilati] -->

---

## Metadata

```
created:   2026-04-03T16:00:00Z
updated:   2026-04-03T16:00:00Z
assignee:  Executor
status:    TODO
wave:      2
milestone: M1
risk_tier: LOW
```

---

## Obiettivo

Completare e far passare la suite PHPUnit M1: verificare tutti i comportamenti
nuovi (correlation_id, alert engine, auth, SNS validation) e aggiornare i test
M0 esistenti che richiedono ora le credenziali di autenticazione.
`make test` deve terminare con exit code 0 e zero deprecazioni.

---

## Scope

| Classe | Metodo | Assert |
|--------|--------|--------|
| `CorrelationIdMiddlewareTest` | `testHeaderPropagated` | Response contiene `X-Correlation-ID` uguale a quello inviato |
| `CorrelationIdMiddlewareTest` | `testHeaderGeneratedIfAbsent` | Response contiene `X-Correlation-ID` formato UUID v4 |
| `AlertsServiceTest` | `testEvaluateCreatesAlertWhenThresholdExceeded` | Alert creato con severity corretta |
| `AlertsServiceTest` | `testEvaluateSkipsAlertBelowThreshold` | Nessun Alert creato |
| `AlertsServiceTest` | `testEvaluateReturnsNullForUnknownMetric` | Null ritornato |
| `BasicAuthMiddlewareTest` | `testDashboardReturns401WithoutCredentials` | HTTP 401 + WWW-Authenticate |
| `BasicAuthMiddlewareTest` | `testDashboardReturns200WithValidCredentials` | HTTP 200 |
| `BasicAuthMiddlewareTest` | `testHealthIsExemptFromAuth` | HTTP 200 senza credenziali |
| `SnsSignatureValidatorTest` | `testValidSignatureReturnsTrue` | true |
| `SnsSignatureValidatorTest` | `testInvalidSignatureReturnsFalse` | false |
| `SnsSignatureValidatorTest` | `testRejectsCertFromNonAmazonDomain` | false |

- [x] `DashboardControllerTest::testIndexReturns200` — aggiornato per passare
      le credenziali di test (se auth è attiva in test env)
- [x] `MetricsControllerTest::testAddReturns201` — aggiornato per passare auth
- [x] `MetricsControllerTest::testAddReturns422OnInvalidPayload` — aggiornato
- [x] `make test` → exit 0, zero deprecazioni

## Non-scope

- [ ] NON scrivere test E2E con AWS reale (nessuna chiamata di rete esterna)
- [ ] NON scrivere test di performance/load
- [ ] NON scrivere test per Wave 2 feature non ancora implementate
- [ ] NON modificare sorgenti in src/ (solo test)

---

## Risk tier

**LOW** — solo test, nessuna scrittura in produzione, DB di test separato.

---

## Allowed paths

```
tests/TestCase/Middleware/CorrelationIdMiddlewareTest.php
tests/TestCase/Service/AlertsServiceTest.php
tests/TestCase/Middleware/BasicAuthMiddlewareTest.php
tests/TestCase/Service/SnsSignatureValidatorTest.php
tests/TestCase/Controller/DashboardControllerTest.php
tests/TestCase/Controller/MetricsControllerTest.php
tests/Fixture/AlertsFixture.php
phpunit.xml.dist
```

## Forbidden paths

```
src/                              # NON modificare codice sorgente
config/Migrations/
coord/                            # solo Planner/Reviewer
```

---

## Dipendenze

```
BLOCKED_BY: TASK_m1_alert_engine
            TASK_m1_auth
            TASK_m1_aws_integration
BLOCKS:     N/A  (ultimo task della wave 2)

Pre-check:  TASK_m1_alert_engine DONE?     NO → BLOCKED
            TASK_m1_auth DONE?             NO → BLOCKED
            TASK_m1_aws_integration DONE?  NO → BLOCKED
            Tutti e tre DONE → pronto per assegnazione
```

---

## DoD

```bash
# Suite completa con testdox
php8.2 vendor/bin/phpunit --testdox
# → tutti i test ✔ (0 ✘, 0 skipped)

# make test exit 0
make test
# → OK (N tests, N assertions) — exit 0

# Zero deprecazioni
make test 2>&1 | grep -i deprecat
# → nessun output
```

**Criteri DONE:**
- [ ] Tutti i test M1 listati nella tabella sopra: PASS
- [ ] Test M0 aggiornati per compatibilità auth: PASS
- [ ] `make test` → exit 0, zero deprecazioni
- [ ] DB `sdi_ops_monitor_test` usato (non produzione)
- [ ] Nessun test usa rete esterna (SNS validator usa fixture)
- [ ] `coord/HANDOFF_m1_tests_m1.md` creato con `correlation_id`

---

## Comandi verifica (stack-specifici)

```bash
# Test M1 in isolamento
php8.2 vendor/bin/phpunit tests/TestCase/Middleware/ tests/TestCase/Service/
# → tutti PASS

# Suite completa
make test

# Coverage opzionale (non bloccante per DoD)
php8.2 vendor/bin/phpunit --coverage-text
```

---

## Assunzioni

- [A8] `AlertsFixture` necessaria solo se i test verificano persistenza Alert
       via SELECT; se si usa solo `assertResponseCode` può non servire
- [A9] PHP 8.2 runtime
- [A15] Credenziali di test per BasicAuth impostate in setUp() tramite
        `putenv('APP_AUTH_USER=testuser')` / `putenv('APP_AUTH_PASSWORD=testpass')`
- [A19] I test SNS usano fixture di certificato e firma pre-calcolati
        (nessuna chiamata a *.amazonaws.com nei test)
