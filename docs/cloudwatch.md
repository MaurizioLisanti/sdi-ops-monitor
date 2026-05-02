# CloudWatch Operational Guide — sdi-ops-monitor

Stack: CakePHP 5 / AWS Elastic Beanstalk / CloudWatch Logs + Alarms

---

## 1. Log Architecture

| Log file (on EC2) | CloudWatch Log Group | Log Stream |
|---|---|---|
| `/var/app/current/logs/error.log` | `/aws/elasticbeanstalk/sdi-ops-monitor/app` | `{instance_id}/error.log` |
| `/var/app/current/logs/debug.log` | `/aws/elasticbeanstalk/sdi-ops-monitor/app` | `{instance_id}/debug.log` |

The CloudWatch Logs agent (configured via `.ebextensions/05_cloudwatch.config`) streams both files in near-real-time. Log entries are JSON-structured (CakePHP 5 default).

---

## 2. Viewing Logs in the AWS Console

1. Open **CloudWatch** → **Logs** → **Log groups**
2. Search for `/aws/elasticbeanstalk/sdi-ops-monitor/app`
3. Click the log group to see log streams (one per EC2 instance)
4. Select a stream to browse log entries

**Live tail (real-time):**
1. In the log group, click **Live tail**
2. Optionally add a filter pattern (e.g., `{ $.level = "error" }`)
3. Click **Start**

**CloudWatch Logs Insights query:**
```
fields @timestamp, level, message, correlationId
| filter level = "error"
| sort @timestamp desc
| limit 50
```
Run from **CloudWatch → Logs Insights**, selecting log group `/aws/elasticbeanstalk/sdi-ops-monitor/app`.

---

## 3. Metric Filter

A metric filter named `sdi-ops-ErrorFilter` counts JSON log entries where `$.level = "error"` and publishes them to:

| Namespace | Metric | Unit |
|---|---|---|
| `sdi-ops-monitor` | `ErrorCount` | Count |

Each matching log line increments the metric by 1. When no errors occur in a period, the `DefaultValue: 0` ensures the metric reports zero (no missing data gaps).

---

## 4. Operational Alarms

### 4.1 `sdi-ops-ErrorRate`

| Property | Value |
|---|---|
| Condition | `ErrorCount > 10` per minute for **2** consecutive periods |
| Period | 60 seconds |
| Missing data | `notBreaching` (silence = no alarm) |
| Action | SNS → `sdi-ops-alerts` |

Triggers when the application logs more than 10 errors in two consecutive minutes. This indicates a recurring failure, not a single transient error.

### 4.2 `sdi-ops-EBHealth`

| Property | Value |
|---|---|
| Metric | `AWS/ElasticBeanstalk :: EnvironmentHealth` |
| Condition | `EnvironmentHealth > 0` for **5** consecutive minutes |
| Period | 60 seconds |
| Missing data | `notBreaching` |
| Action | SNS → `sdi-ops-alerts` |

EB health numeric values:

| Value | Status |
|---|---|
| 0 | Ok |
| 1 | Info |
| 5 | Unknown |
| 10 | NoData |
| 20 | Warning |
| 25 | Degraded |
| 30 | Severe |

Triggers on any state other than `Ok` persisting for 5 minutes.

---

## 5. SNS Topic — Email Alert Subscription

The topic `sdi-ops-alerts` is created automatically on `eb deploy`. To add an email subscription:

1. Open **SNS** → **Topics** → `sdi-ops-alerts`
2. Click **Create subscription**
3. Protocol: **Email**
4. Endpoint: operator email address
5. Click **Create subscription**
6. Confirm the subscription link sent to the email inbox

Email subscriptions are **not managed in code** — they are configured manually per environment to avoid committing personal addresses to the repository.

---

## 6. Silencing an Alarm

To suppress an alarm during a planned maintenance window:

**Console:**
1. Open **CloudWatch** → **Alarms**
2. Select the alarm → **Actions** → **Edit**
3. Enable **Suppress alarm actions** and set a time range

**AWS CLI:**
```bash
aws cloudwatch disable-alarm-actions \
  --alarm-names sdi-ops-ErrorRate sdi-ops-EBHealth
```

Re-enable after maintenance:
```bash
aws cloudwatch enable-alarm-actions \
  --alarm-names sdi-ops-ErrorRate sdi-ops-EBHealth
```

---

## 7. AWS CLI Verification Commands

```bash
# Verify log group exists
aws logs describe-log-groups \
  --log-group-name-prefix "/aws/elasticbeanstalk/sdi-ops-monitor" \
  --query "logGroups[*].logGroupName"

# List active log streams in the app log group
aws logs describe-log-streams \
  --log-group-name "/aws/elasticbeanstalk/sdi-ops-monitor/app" \
  --order-by LastEventTime \
  --descending \
  --query "logStreams[*].{stream:logStreamName,last:lastEventTimestamp}"

# Tail recent error log entries (last 5 minutes)
aws logs filter-log-events \
  --log-group-name "/aws/elasticbeanstalk/sdi-ops-monitor/app" \
  --filter-pattern '{ $.level = "error" }' \
  --start-time $(date -d '5 minutes ago' +%s000) \
  --query "events[*].message"

# Verify metric filter
aws logs describe-metric-filters \
  --log-group-name "/aws/elasticbeanstalk/sdi-ops-monitor/app" \
  --query "metricFilters[*].{name:filterName,pattern:filterPattern}"

# Check alarm states
aws cloudwatch describe-alarms \
  --alarm-name-prefix "sdi-ops-" \
  --query "MetricAlarms[*].{Name:AlarmName,State:StateValue,Reason:StateReason}"

# Get current EnvironmentHealth metric value
aws cloudwatch get-metric-statistics \
  --namespace AWS/ElasticBeanstalk \
  --metric-name EnvironmentHealth \
  --dimensions Name=EnvironmentName,Value=sdi-ops-monitor-env \
  --start-time $(date -u -d '10 minutes ago' +%Y-%m-%dT%H:%M:%SZ) \
  --end-time $(date -u +%Y-%m-%dT%H:%M:%SZ) \
  --period 60 \
  --statistics Maximum \
  --query "Datapoints[*].{time:Timestamp,health:Maximum}"

# Verify CloudWatch agent is running on the instance (via SSM)
aws ssm send-command \
  --document-name "AWS-RunShellScript" \
  --targets "Key=tag:elasticbeanstalk:environment-name,Values=sdi-ops-monitor-env" \
  --parameters 'commands=["systemctl status amazon-cloudwatch-agent"]'
```

---

## 8. Troubleshooting

**Agent not streaming logs:**
- SSH into the EB instance and check: `systemctl status amazon-cloudwatch-agent`
- Review agent logs: `cat /opt/aws/amazon-cloudwatch-agent/logs/amazon-cloudwatch-agent.log`
- Re-run config fetch: `/opt/aws/amazon-cloudwatch-agent/bin/amazon-cloudwatch-agent-ctl -a fetch-config -m ec2 -s -c file:/opt/aws/amazon-cloudwatch-agent/etc/amazon-cloudwatch-agent.json`

**Alarm stuck in INSUFFICIENT_DATA:**
- No logs have been written yet, or the metric filter has not matched any entries
- Generate a test log entry: `curl -X POST https://<env-url>/api/metrics` (triggers app activity)
- Wait 1–2 minutes for CloudWatch to receive data points

**Log group missing after deploy:**
- Check EB CloudFormation stack events: `eb events` or open **CloudFormation** → stack for the EB environment
- Confirm IAM role attached to the EC2 instance has `logs:CreateLogGroup`, `logs:CreateLogStream`, `logs:PutLogEvents` permissions
