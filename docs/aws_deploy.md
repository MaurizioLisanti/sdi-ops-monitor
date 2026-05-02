# Guida Deploy su AWS Elastic Beanstalk — sdi-ops-monitor

## Prerequisiti

### Strumenti richiesti
- **AWS CLI** v2: [installazione](https://docs.aws.amazon.com/cli/latest/userguide/install-cliv2.html)
- **EB CLI**: `pip install awsebcli`
- **Composer**: disponibile nella PATH
- **Git**: repository inizializzato

### Credenziali AWS
Configura le credenziali prima di procedere:
```bash
aws configure
# AWS Access Key ID: <inserisci>
# AWS Secret Access Key: <inserisci>
# Default region name: eu-west-1
# Default output format: json
```

In alternativa, usa variabili d'ambiente:
```bash
export AWS_ACCESS_KEY_ID=...
export AWS_SECRET_ACCESS_KEY=...
export AWS_DEFAULT_REGION=eu-west-1
```

---

## eb init — Inizializzazione progetto

Esegui dalla root del repository:
```bash
eb init sdi-ops-monitor \
  --platform "PHP 8.2 running on 64bit Amazon Linux 2023" \
  --region eu-west-1
```

Questo comando crea/aggiorna `.elasticbeanstalk/config.yml`. Rispondi `No` quando chiede di configurare CodeCommit se non lo usi.

---

## eb create — Creazione ambiente

```bash
eb create sdi-ops-monitor-prod \
  --instance-type t3.small \
  --single \
  --envvars APP_NAME=sdi-ops-monitor,APP_ENV=production
```

Opzioni comuni:
| Flag | Descrizione |
|------|-------------|
| `--instance-type` | Tipo EC2 (es. `t3.small`, `t3.medium`) |
| `--single` | Single-instance (no load balancer, più economico) |
| `--elb-type application` | Application Load Balancer (per prod con scaling) |
| `--min-instances` / `--max-instances` | Auto Scaling group |

---

## eb deploy — Deploy aggiornamenti

```bash
# Deploy del branch corrente
eb deploy sdi-ops-monitor-prod

# Deploy con label esplicita
eb deploy sdi-ops-monitor-prod --label "v1.2.0-$(date +%Y%m%d%H%M)"
```

> Il deploy comprime il codice (escluso `.gitignore`) e lo carica su S3, poi EB esegue i `container_commands` definiti in `.ebextensions/`.

---

## eb logs — Visualizzazione log

```bash
# Ultimi log in streaming
eb logs

# Scarica tutti i log in locale
eb logs --all

# Log in tempo reale (tail)
eb logs --stream
```

I log applicativi CakePHP si trovano in:
- `/var/app/current/logs/` (sul server EB)
- `/var/log/web.stdout.log` (stdout del processo web)

---

## eb terminate — Eliminazione ambiente

```bash
# Elimina l'ambiente (chiede conferma)
eb terminate sdi-ops-monitor-prod

# Elimina anche le risorse associate (S3 bucket, ecc.)
eb terminate sdi-ops-monitor-prod --all
```

> **Attenzione**: questa operazione è irreversibile. Tutti i dati sull'istanza vengono persi.

---

## Variabili d'ambiente

Imposta le variabili nella console AWS EB oppure via CLI:

```bash
eb setenv \
  APP_NAME=sdi-ops-monitor \
  APP_ENV=production \
  SECURITY_SALT=<stringa-casuale-32-char> \
  DATABASE_URL=mysql://user:pass@host/dbname \
  CACHE_DEFAULT_URL=redis://host:6379 \
  LOG_LEVEL=warning
```

### Variabili obbligatorie

| Variabile | Descrizione | Esempio |
|-----------|-------------|---------|
| `APP_ENV` | Ambiente applicativo | `production` |
| `SECURITY_SALT` | Salt per hashing CakePHP (min 32 char) | stringa casuale sicura |
| `DATABASE_URL` | DSN connessione database | `mysql://usr:pwd@rds-host/db` |
| `DEBUG` | Debug mode (deve essere `false` in prod) | `false` |

### Variabili opzionali

| Variabile | Descrizione | Default |
|-----------|-------------|---------|
| `CACHE_DEFAULT_URL` | Redis/Memcached per cache | file system |
| `EMAIL_TRANSPORT_DEFAULT_URL` | SMTP per invio email | — |
| `LOG_LEVEL` | Livello di log minimo | `warning` |

> **Nota di sicurezza**: non inserire mai segreti (password, chiavi API) nel codice o nel repository. Usare esclusivamente le variabili d'ambiente EB o AWS Secrets Manager.

---

## Health check

L'endpoint `/health` è configurato in `.ebextensions/03_healthcheck.config`. Assicurati che la route sia definita in `config/routes.php` e risponda con HTTP 200.

---

## Riferimenti

- [AWS EB CLI documentation](https://docs.aws.amazon.com/elasticbeanstalk/latest/dg/eb-cli3.html)
- [CakePHP deployment guide](https://book.cakephp.org/5/en/deployment.html)
- [PHP platform on EB](https://docs.aws.amazon.com/elasticbeanstalk/latest/dg/create_deploy_PHP.html)
