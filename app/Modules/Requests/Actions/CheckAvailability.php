<?php

namespace App\Modules\Requests\Actions;

use App\Models\ReservationSlotUsage;
use App\Modules\Requests\Support\OpeningSchedule;
use App\Support\Http\ApiException;

/**
 * Read-only: the reservation slots of a date with seats left. Held seats
 * are the pending and confirmed reservations (reservation_slot_usage). A slot
 * can be booked when it still starts after the lead time and has room for
 * the party. Reservation confirmation re-checks capacity under a row lock.
 */
class CheckAvailability
{
    public function __construct(private readonly OpeningSchedule $schedule) {}

    /**
     * @return array{date: string, party_size: int, capacity: int, closed: bool, slots: list<array{slot: string, available_seats: int, can_book: bool}>}
     */
    public function handle(string $date, int $partySize): array
    {
        $setting = $this->schedule->setting();

        if (! OpeningSchedule::isDate($date)) {
            throw new ApiException('validation_failed', 422, ['date' => [__('requests.errors.date_invalid')]]);
        }
        if ($partySize < 1 || $partySize > $setting->maxPartySize()) {
            throw new ApiException('validation_failed', 422, ['party_size' => [__('requests.errors.party_size', ['max' => $setting->maxPartySize()])]]);
        }
        if (! $this->schedule->isWithinWindow($date)) {
            throw new ApiException('validation_failed', 422, ['date' => [__('requests.errors.date_window', ['days' => $setting->maxAdvanceDays()])]]);
        }

        $capacity = $setting->slotCapacity();
        $booked = ReservationSlotUsage::query()
            ->whereDate('date', $date)
            ->pluck('booked_seats', 'slot_start')
            ->map(fn ($seats) => (int) $seats);

        $earliest = $this->schedule->earliestStart();
        $slots = [];
        foreach ($this->schedule->slotsFor($date) as $slot) {
            $left = max(0, $capacity - ($booked[$slot] ?? 0));
            $slots[] = [
                'slot' => $slot,
                'available_seats' => $left,
                'can_book' => $left >= $partySize && $this->schedule->startsAt($date, $slot)->greaterThanOrEqualTo($earliest),
            ];
        }

        return [
            'date' => $date,
            'party_size' => $partySize,
            'capacity' => $capacity,
            'closed' => $slots === [],
            'slots' => $slots,
        ];
    }
}
