# HANDOFF M4-T01 — AWS EB Infrastructure Files

**Task**: M4-T01 — File locali per deploy Elastic Beanstalk  
**Status**: COMPLETE  
**Date**: 2026-05-02

---

## Files Created

| Path | Purpose |
|------|---------|
| `.elasticbeanstalk/config.yml` | EB CLI config: app name, region, platform, branch→environment mapping |
| `Procfile` | Web process: `vendor/bin/cake server -H 0.0.0.0 -p 8080` |
| `.ebextensions/01_php.config` | PHP ini settings via EB option_settings |
| `.ebextensions/02_composer.config` | `composer install --no-dev` as container_command |
| `.ebextensions/03_healthcheck.config` | EB health check URL → `/health` |
| `docs/aws_deploy.md` | Step-by-step deploy guide (init/create/deploy/logs/terminate/envvars) |

## Files Modified

| Path | Change |
|------|--------|
| `.gitignore` | Added `.elasticbeanstalk/*` + `!.elasticbeanstalk/config.yml` |

---

## Configuration Summary

| Key | Value |
|-----|-------|
| Application name | `sdi-ops-monitor` |
| Environment | `sdi-ops-monitor-prod` |
| Region | `eu-west-1` |
| Platform | `PHP 8.2 running on 64bit Amazon Linux 2023` |
| Branch default | `main` → `sdi-ops-monitor-prod` |
| Web port | `8080` |
| PHP memory_limit | `256M` |
| Health check URL | `/health` |

---

## Acceptance Criteria

| Criterion | Result |
|-----------|--------|
| `vendor/bin/phpunit` | PASS — 35 tests, 106 assertions |
| `vendor/bin/phpcs --standard=CakePHP src/` | PASS — no violations |
| All files created at correct paths | PASS |
| No secrets in code | PASS — all credentials via EB env vars |

---

## Notes

- Secrets (DATABASE_URL, SECURITY_SALT, etc.) must be set via `eb setenv` or the EB console — never committed to the repo.
- `.elasticbeanstalk/config.yml` is intentionally tracked (excluded from the wildcard ignore) so the team shares the same EB project config.
- The health check endpoint `/health` must return HTTP 200; ensure the route exists in `config/routes.php`.
