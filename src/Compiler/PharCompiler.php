<?php
/**
 * PHP-to-Binary: Phar Archive Compiler
 * Part of PHP-to-Binary by Anton Barinov <https://github.com/Anton-Barinov>
 */

declare(strict_types=1);

namespace PhpToBinary\Compiler;

use Phar;
use RuntimeException;
use PhpToBinary\Security\SecretFilter;

class PharCompiler
{
    private bool $minify;
    private array $includes;

    public function __construct(bool $minify = false, array $includes = [])
    {
        $this->minify = $minify;
        $this->includes = $includes;
    }

    /**
     * Build an optimized Phar payload file
     * 
     * @param string $entrypointPath Path to main PHP script
     * @param string $outputPharPath Path to output temporary phar
     * @param string $alias Phar alias
     * @return array<string, mixed> Build statistics
     */
    public function compile(string $entrypointPath, string $outputPharPath, string $alias = 'app.phar'): array
    {
        if (ini_get('phar.readonly') === '1') {
            throw new RuntimeException("Phar compilation requires 'phar.readonly = 0'. Run with: php -d phar.readonly=0 bin/php-to-binary ...");
        }

        if (!file_exists($entrypointPath)) {
            throw new RuntimeException("Entrypoint script not found: {$entrypointPath}");
        }

        if (file_exists($outputPharPath)) {
            @unlink($outputPharPath);
        }

        $baseDir = dirname(realpath($entrypointPath));
        $entrypointName = basename($entrypointPath);

        $phar = new Phar($outputPharPath, 0, $alias);
        $phar->startBuffering();

        $filesAdded = 0;
        $totalRawBytes = 0;

        // 1. Add Entrypoint file
        $entryContent = (string)file_get_contents($entrypointPath);
        $totalRawBytes += strlen($entryContent);
        if ($this->minify) {
            $entryContent = Minifier::minify($entryContent);
        }
        $phar->addFromString('entrypoint.php', $entryContent);
        $filesAdded++;

        // 2. Add loose project assets in baseDir (guarded against scanning system temp dir)
        $sysTmp = realpath(sys_get_temp_dir()) ?: sys_get_temp_dir();
        $realBase = realpath($baseDir) ?: $baseDir;

        if ($realBase !== $sysTmp) {
            $allowedAssetExts = ['json', 'yml', 'yaml', 'sql', 'db', 'sqlite', 'xml', 'csv', 'txt', 'ini'];
            $baseFiles = @scandir($baseDir) ?: [];
            foreach ($baseFiles as $bf) {
                $fullPath = $baseDir . '/' . $bf;
                if ($bf === '.' || $bf === '..' || $bf === $entrypointName || is_dir($fullPath)) {
                    continue;
                }
                $ext = strtolower(pathinfo($bf, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedAssetExts, true)) {
                    continue;
                }
                if (SecretFilter::isExcluded($bf)) {
                    continue;
                }
                $content = (string)@file_get_contents($fullPath);
                $phar->addFromString($bf, $content);
                $filesAdded++;
                $totalRawBytes += strlen($content);
            }
        }

        // 3. Add included directories and files (e.g., src, vendor)
        $dirsToScan = $this->includes;
        if (empty($dirsToScan)) {
            // Default check for src, lib, vendor in baseDir
            foreach (['src', 'lib', 'vendor'] as $sub) {
                if (is_dir($baseDir . '/' . $sub)) {
                    $dirsToScan[] = $baseDir . '/' . $sub;
                }
            }
        }

        foreach ($dirsToScan as $dirPath) {
            $realDirPath = realpath($dirPath);
            if (!$realDirPath || !is_dir($realDirPath)) {
                continue;
            }

            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($realDirPath, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($it as $file) {
                /** @var \SplFileInfo $file */
                if (!$file->isFile()) {
                    continue;
                }

                $filePath = $file->getPathname();
                $relPath = ltrim(substr($filePath, strlen($baseDir)), '/\\');

                // Check secret filter
                if (SecretFilter::isExcluded($relPath)) {
                    continue;
                }

                $content = (string)file_get_contents($filePath);
                $totalRawBytes += strlen($content);

                if ($this->minify && $file->getExtension() === 'php') {
                    $content = Minifier::minify($content);
                }

                $phar->addFromString($relPath, $content);
                $filesAdded++;
            }
        }

        // Set Stub with strict Unix LF shebang
        $stub = "#!/usr/bin/env php\n<?php\nPhar::mapPhar('{$alias}');\nrequire 'phar://{$alias}/entrypoint.php';\n__HALT_COMPILER();";
        $phar->setStub($stub);

        $phar->setMetadata([
            'compiled_by' => 'PHP-to-Binary v1.0.0',
            'author'      => 'Anton Barinov',
            'build_time'  => date('Y-m-d H:i:s'),
            'files_count' => $filesAdded,
        ]);

        $phar->stopBuffering();

        return [
            'files_added'     => $filesAdded,
            'raw_bytes'       => $totalRawBytes,
            'phar_size_bytes' => filesize($outputPharPath),
        ];
    }
}
