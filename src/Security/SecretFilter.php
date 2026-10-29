<?php
/**
 * PHP-to-Binary: Secret & Sensitive File Filter
 * Part of PHP-to-Binary by Anton Barinov <https://github.com/Anton-Barinov>
 */

declare(strict_types=1);

namespace PhpToBinary\Security;

class SecretFilter
{
    private const EXCLUDED_PATTERNS = [
        '/\.env(?:\..*)?$/i',
        '/(?:id_rsa|id_ed25519|id_ecdsa|id_dsa)(?:\..*)?$/i',
        '/\.(?:key|pem|p12|pfx|crt|cer)$/i',
        '/(?:config|parameters)\.local\.php$/i',
        '/(?:\.git|\.svn|\.hg|\.DS_Store|Thumbs\.db)$/i',
    ];

    /**
     * Determines whether a relative file path should be excluded from binary packaging
     */
    public static function isExcluded(string $relativePath): bool
    {
        $basename = basename($relativePath);
        foreach (self::EXCLUDED_PATTERNS as $pattern) {
            if (preg_match($pattern, $basename) || preg_match($pattern, $relativePath)) {
                return true;
            }
        }
        return false;
    }
}
