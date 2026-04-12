# TASK_m4_govway_mtls — GovWay/mTLS Basic Configuration

## Metadata

```
created:   2026-04-12T00:00:00Z
updated:   2026-04-12T00:00:00Z
assignee:  Executor
status:    BLOCKED
milestone: M4
wave:      5
```

---

## Obiettivo

Configurare la comunicazione base GovWay/mTLS sull'ambiente EB: HTTPS listener,
gestione `X-Forwarded-Proto` e trusted proxies, documentazione della configurazione
mTLS per il gateway GovWay. Exit: app risponde correttamente su HTTPS;
`APP_FULL_BASE_URL` corretto; `docs/govway_mtls.md` presente con guida configurazione.

---

## Scope

- [ ] `.ebextensions/06_ssl.config` → listener HTTPS 443 su EB (certificato self-signed o ACM)
      Nota: free tier EB non include ALB — usare single-instance con nginx/apache SSL termination
- [ ] `.ebextensions/07_proxy.config` → nginx/apache: set `X-Forwarded-Proto`, `X-Forwarded-For`
- [ ] `config/app.php` → `APP_FULL_BASE_URL` letto da env var; trust proxy headers
      (CakePHP 5: `\Cake\Http\ServerRequest::setTrustedProxies([])`)
- [ ] `config/app.php.example` → aggiornare con `APP_FULL_BASE_URL` placeholder
- [ ] `docs/govway_mtls.md` → guida operativa: setup certificato, config GovWay endpoint,
      headers mTLS attesi, test connettività
- [ ] Placeholder per certificati client mTLS (path config, non cert reali nel repo)

## Non-scope

- NON installa GovWay (infrastruttura esterna — fuori scope)
- NON genera certificati di produzione (guida per operatore, non automazione)
- NON modifica logica auth, routing, controller
- NON tocca `tests/` (nessun nuovo test — configurazione infra)
- NON gestisce certificate rotation (operatore)

---

## Risk tier

**HIGH** — configurazione TLS/SSL su AWS; errore può rendere il sito irraggiungibile
o esporre il servizio senza HTTPS. Richiede revisione umana del config nginx/apache.

---

## Allowed paths

```
.ebextensions/06_ssl.config
.ebextensions/07_proxy.config
config/app.php
config/app.php.example
docs/govway_mtls.md
```

## Forbidden paths

```
src/           (nessuna modifica applicativa)
tests/
config/Migrations/
webroot/       (nessun certificato committato in webroot)
```

---

## Dipendenze

```
BLOCKED_BY:  TASK_m4_eb_infra    (ambiente EB deve esistere)
BLOCKS:      N/A
Pre-check:   TASK_m4_eb_infra status == DONE? → SÌ → pronto / NO → stato BLOCKED
Parallelo con: TASK_m4_env_vars, TASK_m4_cloudwatch (path parzialmente sovrapposti
               su config/app.php — se parallelo: assegnare allo stesso agente in sequenza
               oppure gestire merge su config/app.php)
```

⚠️ **Overlap warning:** `config/app.php` è toccato anche da `TASK_m4_rds_config`.
   Assegnare `govway_mtls` e `rds_config` allo stesso agente in sequenza,
   o applicare le modifiche a `config/app.php` in step separati e fare merge attento.

---

## DoD

```
[ ] .ebextensions/06_ssl.config presente (HTTPS listener configurato)
[ ] .ebextensions/07_proxy.config presente (X-Forwarded-Proto gestito)
[ ] config/app.php legge APP_FULL_BASE_URL da env var
[ ] curl -k https://<eb-url>/health → 200 (HTTPS funzionante)
[ ] curl -H "X-Forwarded-Proto: https" http://<eb-url>/health → nessun redirect loop
[ ] docs/govway_mtls.md presente (≥ 3 sezioni: setup cert, config GovWay, test)
[ ] Nessun certificato reale committato nel repo
[ ] make test PASS (35/35 — nessuna regressione locale)
[ ] HANDOFF_m4_govway_mtls.md creato con correlation_id (UUID v4)
[ ] STATE.json aggiornato: status → DONE, last_updated → <ISO8601>
```

## Comandi verifica

```bash
# Verifica HTTPS su EB
curl -sk https://<eb-url>/health | python3 -m json.tool

# Verifica redirect HTTP → HTTPS (se configurato)
curl -I http://<eb-url>/health | grep -i "location\|301\|302"

# Verifica no certificati committati
find . -name "*.pem" -o -name "*.key" -o -name "*.crt" | grep -v vendor/ | grep -v ".gitignore" \
  && echo "FAIL: certificate files found in repo" || echo "PASS"

# Test suite locale
make test
```

---

## Assunzioni

- [A_M4_14] Free tier EB single-instance → no ALB; SSL termination su nginx/apache dell'istanza EC2
- [A_M4_15] Per M4: self-signed cert accettabile (o ACM free cert se dominio disponibile);
            produzione richiederà cert firmato da CA riconosciuta da GovWay
- [A_M4_16] GovWay gateway si aspetta header `X-GovWay-Transaction-ID` e `X-Correlation-ID` —
            da documentare in govway_mtls.md ma non implementare nel codice in M4
            (implementazione completa mTLS client cert è post-M4)
