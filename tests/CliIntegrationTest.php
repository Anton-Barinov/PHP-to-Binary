<?php
/**
 * CLI Integration Tests for PHP-to-Binary
 * Part of PHP-to-Binary by Anton Barinov <https://github.com/Anton-Barinov>
 */

declare(strict_types=1);

echo "=== Running CLI Integration Test Suite ===\n";

$pass = 0;
$fail = 0;

function assertCli(string $name, bool $condition, string $detail = ''): void {
    global $pass, $fail;
    if ($condition) {
        echo "  [PASS] {$name}" . ($detail ? " -> {$detail}" : "") . "\n";
        $pass++;
    } else {
        echo "  [FAIL] {$name}" . ($detail ? " -> {$detail}" : "") . "\n";
        $fail++;
    }
}

$bin = dirname(__DIR__) . '/bin/php-to-binary';

// 1. Version command
$outVer = shell_exec("php {$bin} --version");
assertCli("Version flag returns v1.0.0", strpos((string)$outVer, 'v1.0.0') !== false, trim((string)$outVer));

// 2. Doctor command
exec("php {$bin} doctor", $outDoc, $codeDoc);
assertCli("Doctor command exits with code 0 (Environment healthy)", $codeDoc === 0);

// 3. Targets command
$outTargets = shell_exec("php {$bin} targets");
assertCli("Targets command lists Linux x86_64", strpos((string)$outTargets, 'linux-x86_64') !== false);
assertCli("Targets command lists macOS arm64", strpos((string)$outTargets, 'macos-arm64') !== false);
assertCli("Targets command lists Windows x64", strpos((string)$outTargets, 'windows-x64') !== false);

// 4. Build command via CLI
$tmpScript = sys_get_temp_dir() . '/cli_test_' . uniqid() . '.php';
$tmpOut = sys_get_temp_dir() . '/cli_out_' . uniqid();
file_put_contents($tmpScript, "<?php echo 'CLI_BUILD_SUCCESS'; ?>");

register_shutdown_function(function() use ($tmpScript, $tmpOut) {
    @unlink($tmpScript);
    @unlink($tmpOut);
});

$buildJson = shell_exec("php {$bin} build {$tmpScript} --output={$tmpOut} --standalone --minify --json");
$data = json_decode((string)$buildJson, true);

assertCli("Build with --json outputs valid status success", is_array($data) && ($data['status'] ?? '') === 'success');
assertCli("Build with --standalone marks is_standalone=true", ($data['is_standalone'] ?? false) === true);

// Execute the generated output binary
$runOut = shell_exec($tmpOut);
assertCli("Generated binary executes and produces expected output", trim((string)$runOut) === 'CLI_BUILD_SUCCESS');

echo "Results: {$pass} Passed, {$fail} Failed\n\n";
if ($fail > 0) exit(1);
