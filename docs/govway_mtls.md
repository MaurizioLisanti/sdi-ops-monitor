# GovWay mTLS — Guida operativa

## 1. Architettura mTLS con GovWay

```
                    ┌─────────────────────────────────────────┐
                    │            AWS Cloud                    │
  Client PA/SDI     │                                         │
  ┌─────────┐  TLS  │  ┌─────────┐  mTLS  ┌──────────────┐  │
  │ Client  │──────►│  │ GovWay  │───────►│ EB Instance  │  │
  │         │  cert │  │ Proxy   │        │ (Apache/PHP) │  │
  └─────────┘  auth │  └─────────┘        └──────────────┘  │
                    │       │                     │           │
                    │       │ X-GovWay-Tx-ID      │           │
                    │       │ X-Correlation-ID    │           │
                    │       └────────────────────►│           │
                    │                             │           │
                    │                        ┌────▼─────┐    │
                    │                        │   RDS    │    │
                    │                        │  MySQL   │    │
                    │                        └──────────┘    │
                    └─────────────────────────────────────────┘
```

**Flusso:**
1. Il client PA presenta il proprio certificato x.509 a GovWay (TLS mutuo lato entry).
2. GovWay verifica il certificato client contro la propria CA trust store.
3. GovWay inoltra la richiesta all'applicazione EB tramite mTLS (server-side TLS) aggiungendo gli header di correlazione.
4. Apache su EB riceve la richiesta, confida gli header proxy (`X-Forwarded-For`, `X-Correlation-ID`) e le passa a CakePHP.
5. CakePHP registra `X-Correlation-ID` in ogni log entry per tracciabilità end-to-end.

---

## 2. Headers attesi da GovWay

| Header | Descrizione | Esempio |
|--------|-------------|---------|
| `X-GovWay-Transaction-ID` | ID univoco della transazione GovWay | `gw-txn-abc123def456` |
| `X-Correlation-ID` | ID correlazione propagato end-to-end | `550e8400-e29b-41d4-a716-446655440000` |
| `X-Forwarded-For` | IP originale del client PA | `203.0.113.42` |
| `X-Forwarded-Proto` | Protocollo originale (`https`) | `https` |

L'applicazione legge `X-Correlation-ID` in `src/Middleware/CorrelationIdMiddleware.php` e lo
propaga a tutti i log strutturati (campo `correlation_id`).

---

## 3. Setup certificato self-signed per M4

> **Nota M4:** In M4 si usa un certificato self-signed per il server EB. In produzione è
> necessario un certificato firmato da una CA riconosciuta da GovWay.

### 3.1 Generare il certificato self-signed

```bash
# Creare la directory dei certificati (non committare i file .pem nel repo)
mkdir -p /etc/ssl/sdi-ops

# Generare chiave privata RSA 2048-bit
openssl genrsa -out /etc/ssl/sdi-ops/server.key 2048

# Generare CSR (adattare CN all'hostname EB)
openssl req -new \
  -key /etc/ssl/sdi-ops/server.key \
  -out /etc/ssl/sdi-ops/server.csr \
  -subj "/C=IT/ST=Rome/L=Rome/O=SDI-Ops/CN=sdi-ops.elasticbeanstalk.com"

# Firmare il certificato (valido 365 giorni)
openssl x509 -req \
  -days 365 \
  -in /etc/ssl/sdi-ops/server.csr \
  -signkey /etc/ssl/sdi-ops/server.key \
  -out /etc/ssl/sdi-ops/server.crt

# Verificare il certificato generato
openssl x509 -in /etc/ssl/sdi-ops/server.crt -text -noout | head -20
```

### 3.2 Path certificati (placeholder)

| File | Path sull'istanza EB | Note |
|------|---------------------|------|
| Chiave privata server | `/etc/ssl/sdi-ops/server.key` | Generata via `.ebextensions` o pre-caricata su S3 |
| Certificato server | `/etc/ssl/sdi-ops/server.crt` | Self-signed per M4 |
| CA GovWay (client auth) | `/etc/ssl/sdi-ops/govway-ca.crt` | **Post-M4** — fornito da GovWay |
| Certificato client | `/etc/ssl/sdi-ops/client.crt` | **Post-M4** — per mTLS client-side |
| Chiave client | `/etc/ssl/sdi-ops/client.key` | **Post-M4** — non committare nel repo |

> ⚠️ **Sicurezza:** Non includere mai file `.key`, `.crt` o `.pem` reali nel repository git.
> Usare AWS Secrets Manager, SSM Parameter Store o S3 bucket privato per distribuire i certificati.

---

## 4. Configurazione GovWay endpoint

### 4.1 Registrazione API su GovWay

1. Accedere alla console GovWay: `https://<govway-host>/govway-console`
2. Navigare in **Registro API → Aggiungi API**
3. Configurare:
   - **Nome API:** `sdi-ops-monitor`
   - **Versione:** `1.0`
   - **Tipo:** `REST`
   - **Endpoint backend:** `https://<eb-env>.elasticbeanstalk.com`

### 4.2 Configurare connettore mTLS su GovWay

Nel connettore verso il backend EB:

```xml
<!-- Estratto configurazione GovWay — adattare al proprio ambiente -->
<connettore>
  <tipo>http</tipo>
  <url>https://sdi-ops.elasticbeanstalk.com</url>
  <https>
    <certificato-client>
      <archivio>/path/to/client.p12</archivio>
      <tipo>pkcs12</tipo>
      <password>${CLIENT_KEYSTORE_PASSWORD}</password>
    </certificato-client>
    <trust-store>
      <archivio>/path/to/server-ca.jks</archivio>
      <password>${TRUSTSTORE_PASSWORD}</password>
    </trust-store>
  </https>
</connettore>
```

### 4.3 Headers di correlazione GovWay

GovWay invia automaticamente `X-GovWay-Transaction-ID`. Per propagare `X-Correlation-ID`:

1. Console GovWay → **Erogazioni → sdi-ops-monitor → Configurazione → Trasformazioni**
2. Aggiungere regola: _Aggiungi header_ `X-Correlation-ID` con valore `${govway:transaction-id}`

---

## 5. Test connettività

### 5.1 Test health endpoint via GovWay

```bash
# Test senza mTLS (verifica base)
curl -v https://<eb-env>.elasticbeanstalk.com/health

# Test tramite GovWay con header di correlazione
curl -v \
  -H "X-Correlation-ID: test-$(date +%s)" \
  https://<govway-host>/govway/rest/sdi-ops-monitor/v1/health

# Verifica presenza header di risposta
curl -sI https://<govway-host>/govway/rest/sdi-ops-monitor/v1/health \
  | grep -i "x-govway\|x-correlation"
```

### 5.2 Test mTLS con certificato client

```bash
# Test connessione mTLS verso EB (post-M4)
curl -v \
  --cert /etc/ssl/sdi-ops/client.crt \
  --key /etc/ssl/sdi-ops/client.key \
  --cacert /etc/ssl/sdi-ops/govway-ca.crt \
  https://<eb-env>.elasticbeanstalk.com/health
```

### 5.3 Verifica header nel log applicativo

```bash
# Su istanza EB via SSH o EB CLI
eb ssh <env-name>
tail -f /var/app/current/logs/debug.log | grep correlation_id

# Via CloudWatch Logs Insights
fields @timestamp, correlation_id, message
| filter correlation_id like /test-/
| sort @timestamp desc
| limit 10
```

### 5.4 Verifica X-Forwarded-For

```bash
# Chiamata con header fittizio — verificare che Apache lo trasmetta
curl -H "X-Forwarded-For: 203.0.113.1" https://<eb-env>.elasticbeanstalk.com/health

# Verificare nei log Apache
tail -f /var/log/httpd/access_log | grep 203.0.113.1
```

---

## 6. Variabili d'ambiente richieste

Impostare tramite EB Console → **Configuration → Software → Environment properties**:

| Variabile | Valore | Note |
|-----------|--------|------|
| `APP_FULL_BASE_URL` | `https://sdi-ops.elasticbeanstalk.com` | URL completo dell'app |
| `TRUSTED_PROXIES` | `127.0.0.1,10.0.0.0/8` | IP/range del proxy GovWay — separati da virgola |

---

## 7. Troubleshooting

| Problema | Causa probabile | Soluzione |
|----------|----------------|-----------|
| `SSL_ERROR_RX_RECORD_TOO_LONG` | HTTP invece di HTTPS | Verificare porta 443 aperta nel SG |
| `certificate verify failed` | CA non riconosciuta | Aggiungere CA GovWay al trust store Apache |
| Header `X-Correlation-ID` assente nei log | Middleware non attivo | Verificare `CorrelationIdMiddleware` registrato in `Application.php` |
| IP client errato nei log | `RemoteIPHeader` non configurato | Verificare `.ebextensions/07_ssl.config` applicato |
| `403 Forbidden` su richieste GovWay | mTLS rifiutato | Verificare `SSLVerifyClient` e CA trust store |
