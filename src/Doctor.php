<?php
/**
 * PHP-to-Binary: System & Environment Diagnostic Doctor
 * Part of PHP-to-Binary by Anton Barinov <https://github.com/Anton-Barinov>
 */

declare(strict_types=1);

namespace PhpToBinary;

use PhpToBinary\Runtime\EngineRegistry;

class Doctor
{
    public static function diagnose(): array
    {
        $checks = [];

        // 1. PHP Version
        $checks['php_version'] = [
            'name'   => 'PHP Version >= 8.0',
            'status' => version_compare(PHP_VERSION, '8.0.0', '>='),
            'detail' => PHP_VERSION,
        ];

        // 2. phar.readonly
        $pharReadonly = ini_get('phar.readonly');
        $checks['phar_readonly'] = [
            'name'   => 'phar.readonly disabled (= 0)',
            'status' => ($pharReadonly === '0' || $pharReadonly === '' || $pharReadonly === false),
            'detail' => ($pharReadonly === '1') ? 'Enabled (Run with php -d phar.readonly=0)' : 'Disabled (Writable)',
        ];

        // 3. Extensions
        foreach (['phar', 'zlib', 'json'] as $ext) {
            $checks['ext_' . $ext] = [
                'name'   => "Extension '{$ext}' loaded",
                'status' => extension_loaded($ext),
                'detail' => extension_loaded($ext) ? 'Loaded' : 'Missing',
            ];
        }

        // 4. Host Target Architecture
        $target = EngineRegistry::detectHostTarget();
        $checks['host_target'] = [
            'name'   => 'Detected Host Architecture',
            'status' => true,
            'detail' => $target,
        ];

        return $checks;
    }
}
