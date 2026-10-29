<?php
/**
 * Minifier Unit Tests
 * Part of PHP-to-Binary by Anton Barinov <https://github.com/Anton-Barinov>
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Compiler/Minifier.php';

use PhpToBinary\Compiler\Minifier;

echo "=== Running Minifier Test Suite ===\n";

$pass = 0;
$fail = 0;

function assertMinify(string $name, bool $condition, string $detail = ''): void {
    global $pass, $fail;
    if ($condition) {
        echo "  [PASS] {$name}" . ($detail ? " -> {$detail}" : "") . "\n";
        $pass++;
    } else {
        echo "  [FAIL] {$name}" . ($detail ? " -> {$detail}" : "") . "\n";
        $fail++;
    }
}

// 1. Strip comments
$src1 = "<?php\n// Single line comment\n/* Multi-line\ncomment */\necho 'hello';\n# Hash comment\n";
$out1 = Minifier::minify($src1);
assertMinify("Strips single-line and multi-line comments", strpos($out1, 'comment') === false && strpos($out1, "echo 'hello';") !== false);

// 2. Preserve HEREDOC and NOWDOC
$src2 = <<<'CODE'
<?php
$str = <<<EOD
Example of string
spanning multiple lines
with /* fake comment */ inside.
EOD;
echo $str;
CODE;
$out2 = Minifier::minify($src2);
assertMinify("Preserves HEREDOC content and internal delimiters", strpos($out2, '/* fake comment */') !== false);

// 3. Keep essential spacing between PHP keywords
$src3 = "<?php echo 'a'; return 42; class Test extends Base implements Iface {}";
$out3 = Minifier::minify($src3);
assertMinify("Preserves keyword spaces (class, extends, implements, return)", strpos($out3, 'class Test extends Base implements Iface') !== false && strpos($out3, 'return 42') !== false);

echo "Results: {$pass} Passed, {$fail} Failed\n\n";
if ($fail > 0) exit(1);
