<?php
/**
 * BinaryBuilder & Compilation Unit Tests
 * Part of PHP-to-Binary by Anton Barinov <https://github.com/Anton-Barinov>
 */

declare(strict_types=1);

if (ini_get('phar.readonly') === '1') {
    passthru(PHP_BINARY . ' -d phar.readonly=0 ' . __FILE__, $code);
    exit($code);
}

require_once dirname(__DIR__) . '/src/Security/SecretFilter.php';
require_once dirname(__DIR__) . '/src/Compiler/Minifier.php';
require_once dirname(__DIR__) . '/src/Runtime/EngineRegistry.php';
require_once dirname(__DIR__) . '/src/Compiler/PharCompiler.php';
require_once dirname(__DIR__) . '/src/Compiler/BinaryBuilder.php';

use PhpToBinary\Compiler\BinaryBuilder;

echo "=== Running BinaryBuilder Test Suite ===\n";

$pass = 0;
$fail = 0;

function assertBuilder(string $name, bool $condition, string $detail = ''): void {
    global $pass, $fail;
    if ($condition) {
        echo "  [PASS] {$name}" . ($detail ? " -> {$detail}" : "") . "\n";
        $pass++;
    } else {
        echo "  [FAIL] {$name}" . ($detail ? " -> {$detail}" : "") . "\n";
        $fail++;
    }
}

$tmpDir = sys_get_temp_dir() . '/p2b_test_' . uniqid();
mkdir($tmpDir . '/src', 0777, true);

// Create sample app entrypoint
$entryCode = <<<'CODE'
<?php
// Sample CLI Script
echo "P2B_EXECUTION_SUCCESS_TOKEN:42\n";
exit(42);
CODE;

file_put_contents($tmpDir . '/main.php', $entryCode);
file_put_contents($tmpDir . '/src/Helper.php', "<?php namespace App; class Helper {}");

register_shutdown_function(function() use ($tmpDir) {
    @unlink($tmpDir . '/main.php');
    @unlink($tmpDir . '/src/Helper.php');
    @rmdir($tmpDir . '/src');
    @unlink($tmpDir . '/app-phar');
    @unlink($tmpDir . '/app-standalone');
    @rmdir($tmpDir);
});

// 1. Compile in Phar Mode
$builder1 = new BinaryBuilder('auto', false, true);
$res1 = $builder1->build($tmpDir . '/main.php', $tmpDir . '/app-phar');

assertBuilder("Compiles executable binary with success status", $res1['status'] === 'success');
assertBuilder("Binary file created on disk with > 0 bytes", file_exists($tmpDir . '/app-phar') && filesize($tmpDir . '/app-phar') > 0);

// Execute compiled binary directly
exec($tmpDir . '/app-phar', $out1, $code1);
assertBuilder("Direct execution succeeds with output token", implode("\n", $out1) === 'P2B_EXECUTION_SUCCESS_TOKEN:42');
assertBuilder("Exit code 42 faithfully forwarded to shell", $code1 === 42, "Code was {$code1}");

// 2. Compile in Standalone Mode
$builder2 = new BinaryBuilder('auto', true, true);
$res2 = $builder2->build($tmpDir . '/main.php', $tmpDir . '/app-standalone');

assertBuilder("Compiles standalone binary bundle", $res2['is_standalone'] === true);
assertBuilder("Standalone binary file created on disk", file_exists($tmpDir . '/app-standalone'));

exec($tmpDir . '/app-standalone', $out2, $code2);
assertBuilder("Standalone binary executes autonomously", in_array('P2B_EXECUTION_SUCCESS_TOKEN:42', $out2, true));
assertBuilder("Standalone exit code 42 forwarded to shell", $code2 === 42, "Code was {$code2}");

echo "Results: {$pass} Passed, {$fail} Failed\n\n";
if ($fail > 0) exit(1);
