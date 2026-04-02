# TASK_m0_health_endpoint — GET /health probe

**Status**: TODO  
**Wave**: 1 · Milestone: M0  
**Risk tier**: LOW  
**Dipendenze**: TASK_scaffold_m0_boot

---

## Scope

Implementare l'endpoint `GET /health` che risponde `{"status":"ok"}` quando
l'app è up, e `{"status":"error","detail":"..."}` con HTTP 503 se il DB non risponde.

## Deliverable

- `HealthController::check()` fa ping al DB via ConnectionManager
- Risposta 200 se ping OK, 503 se ping fallisce
- Content-Type: application/json
- Latenza p95 < 500ms

## DoD

```bash
curl -s http://localhost:8080/health
# → {"status":"ok"}  HTTP 200
```

## Note

- Escludere CSRF protection per questa route (già configurata in routes.php)
- TODO (Planner): aggiungere check memoria/disk se richiesto da SRE team
