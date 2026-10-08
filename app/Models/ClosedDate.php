<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A date the restaurant is closed for reservations and orders (managed in
 * /admin/settings).
 */
#[Fillable(['date', 'reason', 'created_by'])]
class ClosedDate extends Model
{
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
        ];
    }
}
