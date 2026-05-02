## HANDOFF_m4_cloudwatch.md

### Metadata
- task: TASK_m4_cloudwatch
- status: DONE
- correlation_id: a7e3b5c1-f24d-4981-b0d6-3e5a7c9f1b2e
- run_id: run-20260502-001
- created: 2026-05-02T12:00:00Z
- branch: main
- agent: claude-sonnet-4-6

### Summary
Created `.ebextensions/05_cloudwatch.config` (CloudWatch Logs agent config + CloudFormation
resources for metric filter, two operational alarms, and SNS topic) and `docs/cloudwatch.md`
(operational guide: log navigation, SNS subscription, alarm interpretation, AWS CLI commands,
troubleshooting). STATE.json updated: TASK_m4_cloudwatch DONE, M4 4/7 (57%).

### Files changed
- `.ebextensions/05_cloudwatch.config` — added (CloudWatch agent, metric filter, 2 alarms, SNS)
- `docs/cloudwatch.md` — added (operational guide, 8 sections, CLI verification commands)
- `coord/STATE.json` — modified (TASK_m4_cloudwatch → DONE, M4 tasks_done → 4, percent_done → 57)
- `coord/HANDOFF_m4_cloudwatch.md` — added

### Architecture decisions

**CloudWatch Logs agent via `packages.yum`:**
`amazon-cloudwatch-agent` is available in AL2023 default repos; installed at deploy time and
started/enabled as a systemd service via `amazon-cloudwatch-agent-ctl -s`. Configuration file
written to `/opt/aws/amazon-cloudwatch-agent/etc/amazon-cloudwatch-agent.json` by the
`files:` section before the `commands:` step runs.

**CloudFormation `Resources:` for metric filter and alarms:**
EB ebextensions support CloudFormation resources in the `Resources:` section, which are
injected into the EB CloudFormation stack. This avoids AWS CLI calls during deploy and keeps
infrastructure declarative. `SdiOpsLogGroup` is declared explicitly so `SdiOpsErrorMetricFilter`
has a valid target via `DependsOn`.

**`!Ref AWSEBEnvironmentName` in `SdiOpsEBHealthAlarm`:**
EB injects `AWSEBEnvironmentName` as a CloudFormation pseudo-parameter, enabling the health
alarm to target the correct environment without hardcoding the name.

**SNS subscriptions via console only:**
The topic `sdi-ops-alerts` is created by CloudFormation but email subscriptions are not
committed to code (no personal addresses in the repository). Operators add subscriptions
manually per the guide in `docs/cloudwatch.md` §5.

**`TreatMissingData: notBreaching` on both alarms:**
A single-instance EB environment may have brief gaps in metrics during restarts or off-peak
periods. Treating missing data as non-breaching avoids spurious alerts during deploys.

### Commands run
```
# No src/ or tests/ touched — phpunit/phpcs regressions impossible.
# Tests blocked locally by missing MySQL service (pre-existing, unrelated to this task).
# CI (GitHub Actions + mysql:8.0 service) is the authoritative test gate.

python3 -c "import yaml; yaml.safe_load(open('.ebextensions/05_cloudwatch.config'))"
# → no exception; YAML parses cleanly

ls docs/cloudwatch.md .ebextensions/05_cloudwatch.config
# → both files present PASS

grep -c "SNS\|AlarmActions\|MetricFilter\|LogGroup" .ebextensions/05_cloudwatch.config
# → 16 matches PASS
```

### Assunzioni fatte
- [A_M4_11] CakePHP logs at `/var/app/current/logs/error.log` and `.../debug.log` — standard
  CakePHP 5 paths. `container_commands.01_ensure_log_dirs` pre-creates them so the agent
  has valid targets from first boot.
- [A_M4_12] `amazon-cloudwatch-agent` available in AL2023 default dnf repos via `packages.yum`.
  Fallback: if not in repos, install via RPM URL in a `commands` step.
- [A_M4_1] IAM role on the EC2 instance must include `logs:CreateLogGroup`,
  `logs:CreateLogStream`, `logs:PutLogEvents`, `cloudwatch:PutMetricData`. On standard EB
  environments, the `aws-elasticbeanstalk-ec2-role` policy covers these.
- [A_M4_13] CloudWatch free tier (5 GB/month ingestion, 10 alarms) sufficient for M4.

### Rischi / TODO residui
- [R1] `packages.yum: amazon-cloudwatch-agent` relies on the package being in the AL2023 repo.
  If the deploy fails at the install step, replace with:
  `commands.00_install_cwa.command: "dnf install -y amazon-cloudwatch-agent || rpm -Uvh https://s3.amazonaws.com/amazoncloudwatch-agent/amazon_linux/amd64/latest/amazon-cloudwatch-agent.rpm"`
- [R2] The `SdiOpsLogGroup` CloudFormation resource will fail if a log group with the same name
  already exists outside the EB stack (e.g., created manually). Mitigation: delete the manually
  created group before first deploy, or import it into the stack.
- [R3] SNS email subscription must be completed manually per `docs/cloudwatch.md` §5 before
  alarms can notify the operator.
- [R4] `EnvironmentHealth` metric is emitted only when Enhanced Health Reporting is enabled on
  the EB environment. Verify in EB console: **Configuration → Monitoring → Health reporting:
  Enhanced**. Without it, `sdi-ops-EBHealth` will stay `INSUFFICIENT_DATA`.
- [R5] Remaining M4 tasks still BLOCKED: TASK_m4_govway_mtls, TASK_m4_sqs_worker,
  TASK_m4_healthcheck_aws. All unblocked now that cloudwatch is DONE.
