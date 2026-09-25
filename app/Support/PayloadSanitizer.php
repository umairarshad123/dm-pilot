<?php

namespace App\Support;

/**
 * Redacts secret-looking keys recursively and optionally truncates long strings (for logs).
 */
final class PayloadSanitizer
{
    public const REDACTED = '[REDACTED]';

    private const SENSITIVE_PATTERN = '/(token|secret|password|passwd|authorization|api[_-]?key|signature|cookie|app_?proof)/i';

    public static function sanitize(mixed $data, ?int $maxStringLength = null): mixed
    {
        if (is_array($data)) {
            $clean = [];

            foreach ($data as $key => $value) {
                $clean[$key] = is_string($key) && self::isSensitiveKey($key)
                    ? self::REDACTED
                    : self::sanitize($value, $maxStringLength);
            }

            return $clean;
        }

        if (is_string($data) && $maxStringLength !== null && mb_strlen($data) > $maxStringLength) {
            return mb_substr($data, 0, $maxStringLength).'…['.mb_strlen($data).' chars]';
        }

        return $data;
    }

    /** Sanitized + truncated variant intended for log context. */
    public static function forLog(mixed $data, int $maxStringLength = 200): mixed
    {
        return self::sanitize($data, $maxStringLength);
    }

    public static function isSensitiveKey(string $key): bool
    {
        return (bool) preg_match(self::SENSITIVE_PATTERN, $key);
    }
}
