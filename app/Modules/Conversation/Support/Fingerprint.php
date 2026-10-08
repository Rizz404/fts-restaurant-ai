<?php

namespace App\Modules\Conversation\Support;

/**
 * SHA-256 over canonical JSON (keys sorted recursively). Used to detect a
 * reused client key with a different payload.
 */
final class Fingerprint
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function of(array $payload): string
    {
        return hash('sha256', json_encode(self::canonical($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private static function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(self::canonical(...), $value);
    }
}
