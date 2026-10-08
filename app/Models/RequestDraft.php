<?php

namespace App\Models;

use App\Modules\Requests\Enums\RequestType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reviewable, unconfirmed request owned by a guest session (valid for 15
 * minutes, edited with an expected revision). Never a request itself; only an
 * explicit confirmation by the guest creates one.
 */
#[Fillable([
    'public_id', 'guest_session_id', 'conversation_id', 'originating_turn_id', 'type', 'team_id', 'payload',
    'contact_name', 'contact_phone', 'contact_email', 'table_label', 'revision', 'payload_fingerprint',
    'expires_at', 'consumed_request_id',
])]
class RequestDraft extends Model
{
    protected function casts(): array
    {
        return [
            'type' => RequestType::class,
            'payload' => 'array',
            'revision' => 'integer',
            'expires_at' => 'immutable_datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * @return BelongsTo<GuestSession, $this>
     */
    public function guestSession(): BelongsTo
    {
        return $this->belongsTo(GuestSession::class);
    }

    /**
     * @return BelongsTo<StaffTeam, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(StaffTeam::class, 'team_id');
    }

    /**
     * @return BelongsTo<GuestRequest, $this>
     */
    public function consumedRequest(): BelongsTo
    {
        return $this->belongsTo(GuestRequest::class, 'consumed_request_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isConsumed(): bool
    {
        return $this->consumed_request_id !== null;
    }
}
