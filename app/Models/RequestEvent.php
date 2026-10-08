<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only change history of a request. Never stores message text.
 */
#[Fillable(['request_id', 'actor_type', 'actor_user_id', 'event_code', 'previous_status', 'next_status', 'metadata', 'created_at'])]
class RequestEvent extends Model
{
    public const UPDATED_AT = null;

    public const ACTOR_GUEST = 'guest';

    public const ACTOR_STAFF = 'staff';

    public const ACTOR_SYSTEM = 'system';

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<GuestRequest, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(GuestRequest::class, 'request_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
