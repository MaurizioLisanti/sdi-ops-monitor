# TASK_m2_fix_sns_e2e_test — W2: integration tests HTTP-level SNS pipeline

<!-- [CREATED: 2026-04-07 — Planner pass M2: W2 from INTEGRATION_REPORT_wave2] -->

---

## Metadata

```
created:   2026-04-07T00:00:00Z
updated:   2026-04-07T00:00:00Z
assignee:  Executor
status:    TODO
wave:      3
milestone: M2
risk_tier: LOW
```

---

## Obiettivo

Chiudere il warning **W2** dell'Integration Report: aggiungere test di integrazione
HTTP-level per la pipeline SNS in `MetricsControllerTest`, coprendo i path che
oggi sono verificati solo tramite unit test di `SnsSignatureValidator` + review del diff.

Refactoring leggero di `MetricsController`: estrarre la creazione di
`SnsSignatureValidator` in un factory method protetto, per permettere override nei test
e abilitare future mock senza DI container.

---

## Scope

- [x] `src/Controller/Api/MetricsController.php` — refactoring minimo:
      estrarre `new SnsSignatureValidator()` in un factory method protetto:
      ```php
      protected function createSnsValidator(): SnsSignatureValidator
      {
          return new SnsSignatureValidator();
      }
      ```
      `handleSnsRequest()` chiama `$this->createSnsValidator()` invece di `new SnsSignatureValidator()`.
      Nessun cambio di comportamento o contratti I/O.

- [x] `tests/TestCase/Controller/MetricsControllerTest.php` — aggiungere 3 test:

  1. **`testSnsNotificationWithNonAmazonCertUrlReturns400`**
     POST `/api/metrics.json` con header `X-Amz-Sns-Message-Type: Notification`
     e body JSON contenente `SigningCertURL: "https://evil.com/cert.pem"`.
     → HTTP 400, `{"error": "Invalid SNS signature"}`.
     (Nessun mock: domain check è sincrono, nessuna chiamata HTTP.)

  2. **`testSnsSubscriptionConfirmationReturns200`**
     POST `/api/metrics.json` con header `X-Amz-Sns-Message-Type: SubscriptionConfirmation`
     e body JSON senza `SubscribeURL` (o con SubscribeURL su dominio non-Amazon →
     handleSubscriptionConfirmation skips silently).
     → HTTP 200, `{"status": "ok"}`.
     (Nessun mock: confirmation senza URL valido è no-op sicuro.)

  3. **`testSnsNotificationWithInvalidSignatureReturns400`**
     Usa una sottoclasse anonima di `MetricsController` (via override `createSnsValidator()`)
     che inietta un `SnsSignatureValidator` con `$httpGet = fn() => false` (fetch fallisce).
     POST `/api/metrics.json` con header Notification + body JSON con SigningCertURL su
     dominio Amazon valido → il validator passa il domain check ma il fetch del cert fallisce
     → `validate()` restituisce false → HTTP 400.
     Oppure: usa `$httpGet = fn() => ''` (corpo vuoto → validazione fallisce → false).

     **Implementazione suggerita per test 3:**
     CakePHP IntegrationTestTrait non supporta DI di controller a runtime.
     Usare approccio "route override in test" oppure testare direttamente l'effetto
     del factory method iniettando un controller mock tramite `ControllerFactory` o
     `RequestHandlerComponent`. Se questo approccio è troppo invasivo, documentarlo
     nel HANDOFF come `[DEFERRED: DI container M2+]` e limitare lo scope a test 1 e 2.

## Non-scope

- [ ] NON implementare DI container (CakePHP 5 services() in Application.php) — M2+
- [ ] NON aggiungere test per `SnsSignatureValidator` (già coperti in SnsSignatureValidatorTest)
- [ ] NON modificare routing o contratti I/O
- [ ] NON toccare altri controller o servizi

---

## Risk tier

**LOW** — nessun cambio di logica business. Il factory method è un refactoring
puramente interno (metodo `protected`, nessun impatto su routing o contratti API).
I test nuovi coprono path già esistenti nel codice.

---

## Allowed paths

```
src/Controller/Api/MetricsController.php
tests/TestCase/Controller/MetricsControllerTest.php
```

## Forbidden paths

```
src/Service/SnsSignatureValidator.php   # non modificare — unit test già coprono
src/Application.php
config/
coord/
```

---

## Dipendenze

```
BLOCKED_BY: N/A
BLOCKS:     N/A

Pre-check:  N/A — task pronto per assegnazione immediata.

Parallelismo:
  Parallelo con TASK_m2_fix_log_consistency — path disgiunti:
    - fix_log_consistency → src/Controller/Api/MetricsController.php (Log calls)
    - fix_sns_e2e_test    → tests/TestCase/Controller/MetricsControllerTest.php (+factory method)
  ATTENZIONE: entrambi toccano MetricsController.php → merge conflict possibile.
  SOLUZIONE: assegnare allo STESSO agente in sequenza (fix_log_consistency prima,
             fix_sns_e2e_test dopo), OPPURE assegnare a due worktree disgiunti e
             fare merge manuale se conflict.
  IN ALTERNATIVA: il Planner può combinare W1+W2+W3 in un unico task LOW-risk.
```

---

## DoD

```bash
# Lint
php8.2 -l src/Controller/Api/MetricsController.php
php8.2 -l tests/TestCase/Controller/MetricsControllerTest.php

# Test SNS controller-level
php8.2 vendor/bin/phpunit tests/TestCase/Controller/MetricsControllerTest.php
# → OK (≥ 4 tests, ≥ N assertions) — i 2 originali + ≥ 2 nuovi SNS tests

# Suite completa — zero regressioni
make test
# → OK (≥ 19 tests) — exit 0
```

**Criteri DONE:**
- [ ] `MetricsController::createSnsValidator()` estratto (factory method `protected`)
- [ ] `testSnsNotificationWithNonAmazonCertUrlReturns400` → PASS (400, no network call)
- [ ] `testSnsSubscriptionConfirmationReturns200` → PASS (200 {"status":"ok"})
- [ ] Test 3 (invalid sig mock) implementato oppure deferito con nota `[DEFERRED]` nel HANDOFF
- [ ] `make test` → exit 0, ≥ 19 tests
- [ ] `coord/HANDOFF_m2_fix_sns_e2e_test.md` creato con `correlation_id`

---

## Comandi verifica

```bash
php8.2 -l src/Controller/Api/MetricsController.php
php8.2 vendor/bin/phpunit tests/TestCase/Controller/MetricsControllerTest.php --testdox
make test
```

---

## Assunzioni

- [A1] CakePHP IntegrationTestTrait supporta invio di header HTTP arbitrari —
       `$this->configRequest(['headers' => ['X-Amz-Sns-Message-Type' => 'Notification']])`.
- [A2] Il body di una richiesta SNS viene inviato come stringa JSON raw nel test —
       potrebbe richiedere `$this->post(..., $jsonString)` con Content-Type application/json.
- [A3] Test 3 può essere marcato `[DEFERRED]` nel HANDOFF se il DI di controller in
       CakePHP 5 IntegrationTest richiede modifiche ad Application.php (fuori scope).
