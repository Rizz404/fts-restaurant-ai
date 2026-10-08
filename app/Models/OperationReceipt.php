<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Idempotency receipt: unique per actor (guest session or user) + operation
 * type + key. Stores the payload fingerprint and a reference to the result,
 * not the response.
 */
#[Fillable(['actor_type', 'actor_id', 'operation_type', 'idempotency_key', 'fingerprint', 'resource_type', 'resource_public_id', 'created_at'])]
class OperationReceipt extends Model
{
    public const UPDATED_AT = null;

    public const ACTOR_GUEST = 'guest_session';

    public const ACTOR_USER = 'user';

    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
        ];
    }
}
