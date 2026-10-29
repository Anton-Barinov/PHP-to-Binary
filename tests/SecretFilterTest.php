<?php
/**
 * SecretFilter Unit Tests
 * Part of PHP-to-Binary by Anton Barinov <https://github.com/Anton-Barinov>
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Security/SecretFilter.php';

use PhpToBinary\Security\SecretFilter;

echo "=== Running SecretFilter Test Suite ===\n";

$pass = 0;
$fail = 0;

function assertFilter(string $name, bool $condition): void {
    global $pass, $fail;
    if ($condition) {
        echo "  [PASS] {$name}\n";
        $pass++;
    } else {
        echo "  [FAIL] {$name}\n";
        $fail++;
    }
}

// Sensitive exclusions
assertFilter("Filters .env", SecretFilter::isExcluded('.env'));
assertFilter("Filters .env.local", SecretFilter::isExcluded('.env.local'));
assertFilter("Filters id_rsa SSH private key", SecretFilter::isExcluded('id_rsa'));
assertFilter("Filters id_ed25519 SSH private key", SecretFilter::isExcluded('id_ed25519'));
assertFilter("Filters server.key TLS private key", SecretFilter::isExcluded('config/ssl/server.key'));
assertFilter("Filters certificate.pem", SecretFilter::isExcluded('certs/bundle.pem'));
assertFilter("Filters config.local.php", SecretFilter::isExcluded('config.local.php'));
assertFilter("Filters .DS_Store", SecretFilter::isExcluded('.DS_Store'));
assertFilter("Filters .git metadata", SecretFilter::isExcluded('.git'));

// Legitimate inclusions
assertFilter("Allows legitimate source app.php", !SecretFilter::isExcluded('app.php'));
assertFilter("Allows composer.json", !SecretFilter::isExcluded('composer.json'));
assertFilter("Allows src/Service.php", !SecretFilter::isExcluded('src/Service.php'));

echo "Results: {$pass} Passed, {$fail} Failed\n\n";
if ($fail > 0) exit(1);
