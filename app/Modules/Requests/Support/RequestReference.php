<?php

namespace App\Modules\Requests\Support;

use App\Models\GuestRequest;
use RuntimeException;

/**
 * Display reference RST-YYYYMMDD-XXXXXXXX (random suffix). The unique index
 * is the real guarantee; generation retries at most three times. The
 * reference is only a label: it never grants access by itself.
 */
final class RequestReference
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public const PATTERN = '/^RST-\d{8}-['.self::ALPHABET.']{8}$/';

    public static function generate(): string
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $suffix = '';
            for ($i = 0; $i < 8; $i++) {
                $suffix .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
            $reference = 'RST-'.now(config('restaurant.timezone'))->format('Ymd').'-'.$suffix;

            if (! GuestRequest::query()->where('reference', $reference)->exists()) {
                return $reference;
            }
        }

        throw new RuntimeException('Could not generate a unique request reference.');
    }

    public static function isValid(string $reference): bool
    {
        return preg_match(self::PATTERN, $reference) === 1;
    }

    /**
     * Upper-case and trim a guest-typed reference.
     */
    public static function normalize(string $reference): string
    {
        return strtoupper(trim($reference));
    }
}
