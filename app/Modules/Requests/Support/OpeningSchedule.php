<?php

namespace App\Modules\Requests\Support;

use App\Models\ClosedDate;
use App\Models\RestaurantSetting;
use App\Modules\Experience\Support\RestaurantSettings;
use Carbon\CarbonImmutable;

/**
 * Opening hours, closed dates, slot grid, and booking window of the
 * restaurant. All times are in the restaurant timezone (config
 * restaurant.timezone); slots are HH:MM strings. Reservation slots start at
 * the opening time and repeat every slot_interval_minutes while they start
 * before closing time; orders use today's opening hours.
 */
final class OpeningSchedule
{
    private const DAY_KEYS = [1 => 'mon', 2 => 'tue', 3 => 'wed', 4 => 'thu', 5 => 'fri', 6 => 'sat', 7 => 'sun'];

    /** @var array<string, bool> */
    private array $closed = [];

    public function __construct(private readonly RestaurantSettings $settings) {}

    public function timezone(): string
    {
        return (string) config('restaurant.timezone');
    }

    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone());
    }

    public function today(): string
    {
        return $this->now()->toDateString();
    }

    public function intervalMinutes(): int
    {
        return max(5, (int) config('restaurant.reservation.slot_interval_minutes'));
    }

    public function setting(): RestaurantSetting
    {
        return $this->settings->get();
    }

    public static function isDate(string $date): bool
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }

    public static function isTime(string $time): bool
    {
        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time) === 1;
    }

    public function isClosedDate(string $date): bool
    {
        return $this->closed[$date] ??= ClosedDate::query()->whereDate('date', $date)->exists();
    }

    /**
     * Opening ranges of a date (empty on a closed date or a closed weekday).
     *
     * @return list<array{open: string, close: string}>
     */
    public function rangesFor(string $date): array
    {
        if (! self::isDate($date) || $this->isClosedDate($date)) {
            return [];
        }

        $day = self::DAY_KEYS[CarbonImmutable::createFromFormat('!Y-m-d', $date, $this->timezone())->dayOfWeekIso];

        return $this->setting()->openingHours()[$day] ?? [];
    }

    /**
     * All reservation slots of a date (before lead time and capacity).
     *
     * @return list<string>
     */
    public function slotsFor(string $date): array
    {
        $slots = [];

        foreach ($this->rangesFor($date) as $range) {
            $current = $this->minutes($range['open']);
            $close = $this->minutes($range['close']);

            while ($current < $close) {
                $slots[] = sprintf('%02d:%02d', intdiv($current, 60), $current % 60);
                $current += $this->intervalMinutes();
            }
        }

        $slots = array_values(array_unique($slots));
        sort($slots);

        return $slots;
    }

    public function isValidSlot(string $date, string $slot): bool
    {
        return in_array($slot, $this->slotsFor($date), true);
    }

    /**
     * Whether a time of day falls inside the opening hours of a date.
     */
    public function isWithinHours(string $date, string $time): bool
    {
        foreach ($this->rangesFor($date) as $range) {
            if ($time >= $range['open'] && $time < $range['close']) {
                return true;
            }
        }

        return false;
    }

    public function startsAt(string $date, string $time): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d H:i', $date.' '.$time, $this->timezone());
    }

    /**
     * Earliest bookable start (now plus the minimum lead time).
     */
    public function earliestStart(): CarbonImmutable
    {
        return $this->now()->addMinutes($this->setting()->minLeadMinutes());
    }

    /**
     * Last bookable date (today plus max_advance_days).
     */
    public function latestDate(): string
    {
        return $this->now()->addDays($this->setting()->maxAdvanceDays())->toDateString();
    }

    public function isWithinWindow(string $date): bool
    {
        return $date >= $this->today() && $date <= $this->latestDate();
    }

    /**
     * Whether the slot can still be requested now: valid slot, inside the
     * booking window, and starting after the lead time.
     */
    public function isBookable(string $date, string $slot): bool
    {
        return self::isDate($date)
            && self::isTime($slot)
            && $this->isWithinWindow($date)
            && $this->isValidSlot($date, $slot)
            && $this->startsAt($date, $slot)->greaterThanOrEqualTo($this->earliestStart());
    }

    private function minutes(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return $hours * 60 + $minutes;
    }
}
