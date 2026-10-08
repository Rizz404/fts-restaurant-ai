<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Seats held in one slot of one day. Created on demand with an upsert and
 * always updated under a row lock (see ConfirmRequestDraft).
 */
#[Fillable(['date', 'slot_start', 'booked_seats'])]
class ReservationSlotUsage extends Model
{
    protected $table = 'reservation_slot_usage';

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'booked_seats' => 'integer',
        ];
    }
}
