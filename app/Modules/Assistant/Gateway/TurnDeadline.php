<?php

namespace App\Modules\Assistant\Gateway;

use Carbon\CarbonImmutable;

/**
 * Absolute deadline for one AI turn (default 60 s from start).
 */
final class TurnDeadline
{
    public function __construct(public readonly CarbonImmutable $at) {}

    public static function startingNow(?int $seconds = null): self
    {
        return new self(CarbonImmutable::now()->addSeconds($seconds ?? (int) config('assistant.deadline_seconds')));
    }

    public function remainingSeconds(): float
    {
        return max(0.0, CarbonImmutable::now()->diffInMilliseconds($this->at, false) / 1000);
    }

    public function expired(): bool
    {
        return $this->remainingSeconds() <= 0.5;
    }
}
