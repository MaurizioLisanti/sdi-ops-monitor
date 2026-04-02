# TASK_m0_metric_ingestion — POST /api/metrics

**Status**: TODO  
**Wave**: 1 · Milestone: M0  
**Risk tier**: MED  
**Dipendenze**: TASK_scaffold_m0_boot

---

## Scope

Implementare `POST /api/metrics` che valida il payload JSON, persiste un record
`Metric` in MySQL e restituisce HTTP 201 con l'ID creato.

## Payload atteso

```json
{
  "source": "aws-ec2-prod-01",
  "name": "cpu_usage",
  "value": 87.4,
  "unit": "percent",
  "tags": {"env": "prod"},
  "recorded_at": "2026-04-02T10:00:00Z"
}
```

## Deliverable

- Validazione in `MetricsTable::validationDefault()` — source, name, value obbligatori
- Errori di validazione → 422 con body `{"errors": {...}}`
- Payload valido → 201 con body `{"id": <int>}`
- Record verificabile in `SELECT * FROM metrics`

## DoD

```bash
curl -s -X POST http://localhost:8080/api/metrics.json \
  -H 'Content-Type: application/json' \
  -d '{"source":"test","name":"cpu","value":50.0,"recorded_at":"2026-04-02T10:00:00Z"}'
# → HTTP 201  {"id": 1}
```

## Note

- [A3] Nessuna autenticazione in M0 — aggiungere in M1
- TODO (Planner): decidere se pubblicare su SNS dopo il save (M1 scope)
- `recorded_at` deve essere parsato come DateTimeImmutable
