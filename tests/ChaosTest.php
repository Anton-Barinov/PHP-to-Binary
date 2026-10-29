<?php
/**
 * PHP-to-Binary Chaos & Stress Test Suite
 */

declare(strict_types=1);

if (ini_get('phar.readonly') === '1') {
    passthru(PHP_BINARY . ' -d phar.readonly=0 ' . __FILE__, $code);
    exit($code);
}

spl_autoload_register(function ($class) {
    $prefix = 'PhpToBinary\\';
    $baseDir = dirname(__DIR__) . '/src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) require_once $file;
});

use PhpToBinary\Compiler\BinaryBuilder;

echo "========================================================\n";
echo "   Running PHP-to-Binary Chaos & Stress Tests\n";
echo "========================================================\n\n";

$passed = 0;
$failed = 0;

function run_test(string $name, callable $fn): void {
    global $passed, $failed;
    echo "Testing {$name}... ";
    try {
        $fn();
        echo "\033[32m[PASS]\033[0m\n";
        $passed++;
    } catch (\Throwable $e) {
        echo "\033[31m[FAIL: " . $e->getMessage() . "]\033[0m\n";
        $failed++;
    }
}

// 1. Test phar.readonly=1 CLI Auto-Respawn
run_test("phar.readonly=1 CLI Auto-Respawn Forwarding", function() {
    $cliBin = dirname(__DIR__) . '/bin/php-to-binary';
    // Explicitly launch with -d phar.readonly=1
    $cmd = PHP_BINARY . " -d phar.readonly=1 {$cliBin} --version";
    exec($cmd, $out, $code);

    assert($code === 0, "CLI should succeed even when initially spawned with phar.readonly=1 (code was {$code})");
    $outStr = implode(' ', $out);
    assert(strpos($outStr, 'PHP-to-Binary v1.0.0') !== false, "Output must contain version header");
});

// 2. Test STDIN Pipe and Argument Forwarding in Compiled Binary
run_test("STDIN Pipe and Complex CLI Arguments Forwarding", function() {
    $tempDir = sys_get_temp_dir() . '/p2b_chaos_' . uniqid();
    mkdir($tempDir, 0755, true);

    $entrypoint = $tempDir . '/pipe_app.php';
    $code = <<<'PHP'
<?php
// Read STDIN
$stdin = trim(file_get_contents('php://stdin'));
// Echo arguments count and STDIN
echo "STDIN_DATA:" . $stdin . "|ARG1:" . ($argv[1] ?? '') . "|ARG2:" . ($argv[2] ?? '');
exit(0);
PHP;
    file_put_contents($entrypoint, $code);

    $outputBinary = $tempDir . '/pipe_binary';
    $builder = new BinaryBuilder('auto', false, false);
    $result = $builder->build($entrypoint, $outputBinary);

    assert($result['status'] === 'success');
    chmod($outputBinary, 0755);

    // Feed piped STDIN and arguments with spaces & quotes
    $pipeCmd = "echo 'hello_stream_payload' | {$outputBinary} 'first arg with spaces' 'second \"quoted\" arg'";
    exec($pipeCmd, $out, $exitCode);

    $outStr = implode(' ', $out);
    assert($exitCode === 0, "Binary exit code should be 0");
    assert(strpos($outStr, 'STDIN_DATA:hello_stream_payload') !== false, "STDIN was not passed into compiled binary");
    assert(strpos($outStr, 'ARG1:first arg with spaces') !== false, "Complex arguments with spaces failed");

    // Clean up
    @unlink($entrypoint);
    @unlink($outputBinary);
    @rmdir($tempDir);
});

// 3. Test Embedded SQLite Database & Asset Packing Stress
run_test("Embedded SQLite Database Asset Execution", function() {
    $tempDir = sys_get_temp_dir() . '/p2b_sqlite_' . uniqid();
    mkdir($tempDir, 0755, true);

    // Create a local SQLite DB with 100 rows
    $dbFile = $tempDir . '/records.db';
    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->exec("CREATE TABLE items (id INTEGER PRIMARY KEY, title TEXT, score REAL);");
    $stmt = $pdo->prepare("INSERT INTO items (title, score) VALUES (:t, :s)");
    for ($i = 1; $i <= 100; $i++) {
        $stmt->execute([':t' => "Item #{$i} " . str_repeat('X', 50), ':s' => $i * 1.5]);
    }
    unset($pdo);

    $entrypoint = $tempDir . '/db_app.php';
    $code = <<<'PHP'
<?php
$embeddedDb = __DIR__ . '/records.db';
if (!file_exists($embeddedDb)) {
    echo "ERROR: records.db not found in " . __DIR__;
    exit(1);
}
$tmpDb = sys_get_temp_dir() . '/extracted_test_' . uniqid() . '.db';
copy($embeddedDb, $tmpDb);

$pdo = new PDO('sqlite:' . $tmpDb);
$count = $pdo->query("SELECT COUNT(*) FROM items")->fetchColumn();
@unlink($tmpDb);
echo "SQLITE_ROWS:" . $count;
exit(0);
PHP;
    file_put_contents($entrypoint, $code);

    $outputBinary = $tempDir . '/db_binary';
    $builder = new BinaryBuilder('auto', false, false);
    $res = $builder->build($entrypoint, $outputBinary);

    assert($res['status'] === 'success');
    chmod($outputBinary, 0755);

    exec($outputBinary, $out, $exitCode);
    $outStr = implode(' ', $out);

    assert($exitCode === 0, "Execution failed");
    assert(strpos($outStr, 'SQLITE_ROWS:100') !== false, "Failed to query embedded SQLite records");

    // Clean up
    @unlink($dbFile);
    @unlink($entrypoint);
    @unlink($outputBinary);
    @rmdir($tempDir);
});

echo "\n--------------------------------------------------------\n";
echo "Chaos Results: {$passed} Passed, {$failed} Failed\n";
echo "--------------------------------------------------------\n";

if ($failed === 0) {
    echo "🎉 ALL PHP-TO-BINARY CHAOS TESTS PASSED CLEANLY!\n";
    exit(0);
} else {
    exit(1);
}
