<?php

namespace App\Modules\Operations\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Last-seen timestamps for background processes (shared cache store). The
 * scheduler beats every minute via restaurant:recover-turns.
 */
final class Heartbeat
{
    public const SCHEDULER = 'scheduler';

    public const WORKER = 'worker';

    public static function beat(string $process): void
    {
        Cache::put(self::key($process), CarbonImmutable::now()->toIso8601String(), now()->addHours(6));
    }

    public static function last(string $process): ?CarbonImmutable
    {
        $value = Cache::get(self::key($process));

        return is_string($value) ? CarbonImmutable::parse($value) : null;
    }

    private static function key(string $process): string
    {
        return 'ops:heartbeat:'.$process;
    }
}
