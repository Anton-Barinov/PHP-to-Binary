<?php
/**
 * PHP-to-Binary: Production Code Minifier & Whitespace Stripper
 * Part of PHP-to-Binary by Anton Barinov <https://github.com/Anton-Barinov>
 */

declare(strict_types=1);

namespace PhpToBinary\Compiler;

class Minifier
{
    /**
     * Safely minify PHP code using PHP token_get_all tokenizer
     */
    public static function minify(string $source): string
    {
        if (trim($source) === '') {
            return $source;
        }

        $tokens = @token_get_all($source);
        if (empty($tokens)) {
            return $source;
        }

        $minified = '';
        $prevToken = null;

        foreach ($tokens as $token) {
            if (is_string($token)) {
                $minified .= $token;
                $prevToken = $token;
                continue;
            }

            $id = $token[0];
            $text = $token[1];

            // Strip comments
            if ($id === T_COMMENT || $id === T_DOC_COMMENT) {
                // If comment ends with newline, preserve a single newline to prevent syntax merging
                if (str_ends_with($text, "\n") || str_ends_with($text, "\r\n")) {
                    $minified .= "\n";
                }
                continue;
            }

            // Collapse multiple whitespaces
            if ($id === T_WHITESPACE) {
                // Keep space between consecutive alphanumeric tokens
                if (is_array($prevToken) && in_array($prevToken[0], [T_VARIABLE, T_STRING, T_ECHO, T_RETURN, T_CONST, T_CLASS, T_FUNCTION, T_NEW, T_INSTANCEOF, T_EXTENDS, T_IMPLEMENTS, T_NAMESPACE, T_USE], true)) {
                    $minified .= ' ';
                } elseif (strpos($text, "\n") !== false) {
                    $minified .= "\n";
                } else {
                    $minified .= ' ';
                }
                continue;
            }

            $minified .= $text;
            $prevToken = $token;
        }

        return $minified;
    }
}
