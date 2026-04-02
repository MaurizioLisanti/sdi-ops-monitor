# TASK_m0_dashboard — GET / dashboard

**Status**: TODO  
**Wave**: 1 · Milestone: M0  
**Risk tier**: LOW  
**Dipendenze**: TASK_scaffold_m0_boot

---

## Scope

Implementare la dashboard principale `GET /` che mostra:
- Contatore metriche ricevute nelle ultime 24h
- Lista degli alert aperti (status = 'open'), ordinati per severity DESC
- Status generale (verde / giallo / rosso) basato su alert aperti

## Deliverable

- `DashboardController::index()` carica dati da MetricsTable e AlertsTable
- Variabili passate alla view: `$metricsCount`, `$openAlerts`, `$overallStatus`
- Template `templates/Dashboard/index.php` renderizza i dati
- `GET /` → HTTP 200 con HTML contenente "SDI Ops Monitor"

## DoD

```bash
curl -s http://localhost:8080/ | grep "SDI Ops Monitor"
# → match trovato
```

## Note

- [A3] Nessuna autenticazione in M0
- TODO (Planner): scegliere CSS framework (Tailwind raccomandato) per M1
- Non implementare grafici in M0 — solo contatori testuali
