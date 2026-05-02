# SQS Worker — Guida Operativa

## Architettura

Il worker SQS è implementato come cron job su EB (ogni minuto) che esegue
`bin/cake sqs_poll`. Consuma messaggi dalla coda SQS e li persiste su RDS.

## Configurazione

Variabili d'ambiente richieste su EB:
- `AWS_SQS_QUEUE_URL` — URL completo della coda SQS
- `AWS_REGION` — regione AWS (es. eu-west-1)

## Monitoraggio

```bash
# Verifica log worker
eb ssh
tail -f /var/log/sqs-worker.log

# Verifica messaggi in coda
aws sqs get-queue-attributes \
  --queue-url https://sqs.eu-west-1.amazonaws.com/958632040298/sdi-ops-monitor-queue \
  --attribute-names ApproximateNumberOfMessages \
  --region eu-west-1
```

## DLQ — Dead Letter Queue (raccomandato per produzione)

Configurare una DLQ per messaggi non processabili:
1. Creare coda `sdi-ops-monitor-queue-dlq`
2. Impostare `maxReceiveCount: 3` sulla coda principale
3. Monitorare DLQ con allarme CloudWatch
