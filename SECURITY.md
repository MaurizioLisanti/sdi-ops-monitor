# Security Policy

## Supported PHP versions

sdi-ops-monitor targets PHP 8.2 and is tested in CI on PHP 8.2, 8.3 and 8.4.

PHP 8.2 receives security fixes only until **31 December 2026**. Plan the move
to PHP 8.3 or 8.4 before that date; the CI matrix already covers both.

## PHP2Shell (CVE-2026-109618, CVE-2026-109619)

PHP2Shell is a pair of critical remote code execution flaws in the PHP engine
itself, fixed on 21 October 2026. They do not depend on application code: what
matters is the PHP version the server runs. Minimum safe versions:

| Branch | Fixed in |
|---|---|
| 8.2 | 8.2.35 |
| 8.3 | 8.3.36 |
| 8.4 | 8.4.27 |
| 8.5 | 8.5.12 |

### How this repository checks it

- CI runs `php bin/check_php_security.php` on every job and fails if the
  runtime is older than the fixed release of its branch.
- You can run the same check on any server:

  ```bash
  php bin/check_php_security.php
  ```

### Distribution packages

Debian, Ubuntu and other distributions often backport security fixes without
changing the PHP version number. On those systems the script can report an
older version that is in fact patched. Before concluding, check the package
changelog for the CVE IDs:

```bash
# Debian / Ubuntu
apt-get changelog php-common | grep -E "CVE-2026-10961[89]"

# Amazon Linux / RHEL-based (package name varies, e.g. php8.2-common)
rpm -q --changelog $(rpm -qa 'php*common*' | head -1) | grep -E "CVE-2026-10961[89]"
```

For this reason `composer.json` keeps `"php": ">=8.2"` instead of requiring
`>=8.2.35`: a stricter constraint would reject patched distribution packages.

### AWS Elastic Beanstalk

When deploying, use a platform version of "PHP 8.2 running on 64bit Amazon
Linux 2023" (or a newer PHP branch) whose PHP includes the fixes above, then
verify on the instance with `php -v` and the script.

## Reporting a vulnerability

Please do not open a public issue for security problems. Use GitHub's
private vulnerability reporting ("Report a vulnerability" in the Security
tab) or contact the maintainer directly.
