<?php

namespace App\Modules\Requests\Support;

use App\Models\GuestRequest;
use App\Models\ReservationSlotUsage;
use App\Modules\Requests\Enums\RequestType;

/**
 * Releases the seats a reservation holds when it reaches a terminal status
 * (declined, cancelled, no_show, completed). Holding happens in
 * ConfirmRequestDraft. Call inside the transaction that changes the status;
 * the slot row is locked and booked_seats never goes below zero.
 */
final class SeatLedger
{
    public static function release(GuestRequest $request): void
    {
        if ($request->type !== RequestType::Reservation || $request->reserved_date === null || $request->reserved_slot === null) {
            return;
        }

        $usage = ReservationSlotUsage::query()
            ->whereDate('date', $request->reserved_date->format('Y-m-d'))
            ->where('slot_start', $request->reserved_slot)
            ->lockForUpdate()
            ->first();

        if ($usage === null) {
            return;
        }

        $usage->forceFill(['booked_seats' => max(0, $usage->booked_seats - (int) $request->party_size)])->save();
    }
}
