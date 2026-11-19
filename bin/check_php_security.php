<?php
declare(strict_types=1);

/**
 * PHP2Shell runtime check (CVE-2026-109618, CVE-2026-109619).
 *
 * Exits with status 1 when the running PHP is older than the first upstream
 * release that fixes PHP2Shell for its branch, or when the branch no longer
 * receives security fixes. Used by CI; can be run on any server:
 *
 *     php bin/check_php_security.php
 *
 * Upstream version numbers only. Debian, Ubuntu and other distributions
 * backport security fixes without changing the PHP version, so on a
 * distribution package a "vulnerable" result means: check the package
 * changelog for the CVE IDs before concluding (see SECURITY.md).
 */

// First release of each supported branch that includes the PHP2Shell fixes
// (PHP security release of 21 October 2026).
$fixedIn = [
    '8.2' => '8.2.35',
    '8.3' => '8.3.36',
    '8.4' => '8.4.27',
    '8.5' => '8.5.12',
];

// Built from the numeric parts only: PHP_VERSION may carry a distribution
// suffix (for example "8.3.6-0ubuntu0.24.04.5") that version_compare() misreads.
$branch = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
$version = $branch . '.' . PHP_RELEASE_VERSION;

if (!isset($fixedIn[$branch])) {
    if (version_compare($branch, '8.5', '>')) {
        echo "PHP {$version}: newer than the branches covered by this check, OK.\n";
        exit(0);
    }
    fwrite(STDERR, "PHP {$version}: branch {$branch} no longer receives security fixes. Upgrade to PHP 8.2 or later.\n");
    exit(1);
}

if (version_compare($version, $fixedIn[$branch], '<')) {
    fwrite(STDERR, "PHP {$version}: vulnerable to PHP2Shell (CVE-2026-109618, CVE-2026-109619). Upgrade to {$fixedIn[$branch]} or later.\n");
    exit(1);
}

echo "PHP {$version}: includes the PHP2Shell fixes (>= {$fixedIn[$branch]}), OK.\n";
exit(0);
