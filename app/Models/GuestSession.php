<?php

namespace App\Models;

use Database\Factories\GuestSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Anonymous guest identity: owns conversations and requests. Holds no
 * personal data; the table label is only a note, never authorization.
 */
#[Fillable(['public_id', 'locale', 'table_label', 'created_at', 'last_seen_at', 'expires_at'])]
class GuestSession extends Model
{
    /** @use HasFactory<GuestSessionFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
            'last_seen_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }
}
