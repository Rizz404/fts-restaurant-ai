<?php

namespace App\Modules\Operations\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Daily count of API responses answered with 429 (limiter hits), kept in the
 * shared cache for 8 days. A number only: no IP, session, or path is stored.
 */
final class RateLimitHits
{
    public static function record(): void
    {
        $key = self::key(now(config('restaurant.timezone'))->toDateString());

        // add() creates the counter with its expiry; increment() keeps it.
        Cache::add($key, 0, now()->addDays(8));
        Cache::increment($key);
    }

    /**
     * Hits over the last N days including today.
     */
    public static function lastDays(int $days): int
    {
        $total = 0;
        for ($i = 0; $i < $days; $i++) {
            $total += (int) Cache::get(self::key(now(config('restaurant.timezone'))->subDays($i)->toDateString()), 0);
        }

        return $total;
    }

    private static function key(string $date): string
    {
        return 'ops:rate_limited:'.$date;
    }
}
