## HANDOFF_m4_govway_mtls.md

### Metadata
- task: TASK_m4_govway_mtls
- status: DONE
- correlation_id: c2e7a4f9-3b81-4d56-a0e2-8f1c5d9b7e3a
- run_id: run-20260502-002
- created: 2026-05-02T18:20:00Z
- branch: main
- agent: claude-sonnet-4-6

### Summary
Created `docs/govway_mtls.md` (operational guide for GovWay mTLS integration),
`.ebextensions/07_ssl.config` (Apache RemoteIP + X-Correlation-ID header passthrough), and
updated `config/app.php` to add the `TrustedProxies` configuration block readable from
`TRUSTED_PROXIES` env var. No src/ or tests/ files were modified. STATE.json updated:
TASK_m4_govway_mtls DONE, M4 5/7 (71%).

### Files changed
- `docs/govway_mtls.md` — added (7-section operational guide: architecture, headers, cert setup,
  GovWay config, connectivity tests, env vars, troubleshooting)
- `.ebextensions/07_ssl.config` — added (RemoteIPHeader trust, X-Correlation-ID passthrough)
- `config/app.php` — modified (TrustedProxies config block added before Security section)
- `coord/STATE.json` — modified (TASK_m4_govway_mtls → DONE, M4 tasks_done → 5, percent_done → 71)
- `coord/HANDOFF_m4_govway_mtls.md` — added

### Architecture decisions

**`07_ssl.config` — RemoteIP + header passthrough only (no cert provisioning):**
Per assumption A_M4_16, M4 configures SSL server-side headers and proxy trust; the actual
mTLS client certificate exchange with GovWay is post-M4. The ebextension installs
`/etc/httpd/conf.d/proxy_headers.conf` with `LoadModule remoteip_module` (available on AL2023
httpd 2.4), `RemoteIPTrustedProxy` for RFC-1918 ranges covering GovWay and AWS infrastructure,
and `RequestHeader set X-Correlation-ID` to forward the GovWay correlation header to PHP.

**`TrustedProxies` config block in `config/app.php`:**
Added a dedicated top-level `TrustedProxies` key (not nested under `App` or `Security`) to
separate proxy trust from application identity. The `ips` array is populated from
`TRUSTED_PROXIES` env var (comma-separated), with `array_filter`/`array_map`/`trim` to handle
empty strings and whitespace. The `headers` list documents which forwarded headers are accepted.
The bootstrap.php or Application middleware can call
`ServerRequest::setTrustedProxies(Configure::read('TrustedProxies.ips'))` to activate it.

**`APP_FULL_BASE_URL` already present:**
`config/app.php` line 62 already contained `'fullBaseUrl' => env('APP_FULL_BASE_URL', false)`.
No change was needed for that requirement.

**Self-signed cert for M4 (not provisioned in code):**
Per assumption A_M4_15, certificate files are never committed to the repository.
`docs/govway_mtls.md` §3 documents the `openssl` commands to generate them on the instance and
the placeholder paths (`/etc/ssl/sdi-ops/`). Production cert provisioning via SSM/Secrets
Manager is noted as a post-M4 activity.

### Commands run
```bash
vendor/bin/phpunit --no-coverage
# → OK (35 tests, 106 assertions)  PASS

vendor/bin/phpcs --standard=CakePHP src/
# → no errors  PASS

ls docs/govway_mtls.md .ebextensions/07_ssl.config
# → both files present  PASS

grep 'TrustedProxies' config/app.php
# → block present  PASS

grep -c 'GOVWAY\|mTLS\|X-Correlation\|X-GovWay' docs/govway_mtls.md
# → 22 matches  PASS
```

### Assunzioni fatte
- [A_M4_15] Self-signed cert accettabile per M4; produzione richiederà cert firmato da CA
  GovWay-compatible. Nessun file `.crt`/`.key` reale nel repo.
- [A_M4_16] GovWay mTLS client cert è post-M4; M4 configura solo SSL server-side headers e
  proxy trust. `07_ssl.config` non abilita `SSLVerifyClient require`.
- [A_M4_14] Free tier EB single-instance senza ALB; SSL termination sull'istanza EC2. Apache
  riceve direttamente le richieste da GovWay.
- `mod_remoteip` è disponibile e caricabile su Amazon Linux 2023 httpd 2.4 via `LoadModule`.
  Se il modulo non è presente come `.so`, il fallback è usare `SetEnvIf X-Forwarded-For`.

### Rischi / TODO residui
- [R1] `LoadModule remoteip_module` fallirà se il `.so` non è presente nel percorso standard.
  Mitigazione: aggiungere `commands.00_check_remoteip: command: "rpm -q mod_remoteip || dnf install -y mod_remoteip || true"`.
- [R2] `TrustedProxies.ips` non è applicato automaticamente da CakePHP 5. L'operatore deve
  aggiungere in `Application.php` middleware:
  `ServerRequest::setTrustedProxies(Configure::read('TrustedProxies.ips'));`
  oppure in `config/bootstrap.php`.
- [R3] Per abilitare mTLS client-side (post-M4), aggiungere a `07_ssl.config`:
  `SSLCACertificateFile /etc/ssl/sdi-ops/govway-ca.crt` e `SSLVerifyClient require`.
- [R4] La variabile `TRUSTED_PROXIES` deve essere impostata in EB Console prima del deploy
  per consentire il corretto parsing dell'IP reale del client.
- [R5] Task rimanenti in M4: TASK_m4_healthcheck_aws (BLOCKED). Da completare per raggiungere
  l'exit condition della wave 5 (M4 100%).
