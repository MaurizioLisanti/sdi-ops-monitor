# TASK_m0_tests_smoke — PHPUnit smoke suite

**Status**: TODO  
**Wave**: 1 · Milestone: M0  
**Risk tier**: LOW  
**Dipendenze**: TASK_m0_health_endpoint, TASK_m0_metric_ingestion, TASK_m0_dashboard

---

## Scope

Completare la suite PHPUnit di smoke test per i tre endpoint M0.
Tutti i test devono passare con `make test`.

## Test da implementare

| Classe | Metodo | Assert |
|--------|--------|--------|
| HealthControllerTest | testHealthReturns200 | HTTP 200, body contiene `"status":"ok"` |
| DashboardControllerTest | testIndexReturns200 | HTTP 200, body contiene "SDI Ops Monitor" |
| MetricsControllerTest | testAddReturns201 | HTTP 201 con payload valido |
| MetricsControllerTest | testAddReturns422OnInvalidPayload | HTTP 422 con payload mancante di `name` |

## DoD

```bash
make test
# → OK (4 tests, N assertions)
# → Exit code 0
```

## Note

- Usare `IntegrationTestTrait` (CakePHP 5) — non `WebTestCase`
- TODO (Planner): aggiungere fixture `MetricsFixture` se i test di lettura lo richiedono
- DB test: `sdi_ops_monitor_test` — configurato in `tests/bootstrap.php`
