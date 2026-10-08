<?php

namespace App\Models;

use App\Modules\Requests\Enums\RequestStatus;
use App\Modules\Requests\Enums\RequestType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A confirmed guest request (named GuestRequest to avoid clashing with the
 * HTTP Request class). The reference is only a label; access always goes
 * through the owning guest session, a session grant, or team membership.
 * Contact details are visible to the owner and the handling team only.
 */
#[Fillable([
    'public_id', 'reference', 'type', 'team_id', 'status', 'version', 'guest_session_id', 'contact_name',
    'contact_phone', 'contact_phone_digits', 'contact_email', 'table_label', 'locale', 'payload', 'pricing',
    'reserved_date', 'reserved_slot', 'party_size', 'assignee_id', 'draft_id', 'conversation_id',
    'confirmed_at', 'terminal_at',
])]
class GuestRequest extends Model
{
    protected function casts(): array
    {
        return [
            'type' => RequestType::class,
            'status' => RequestStatus::class,
            'version' => 'integer',
            'payload' => 'array',
            'pricing' => 'array',
            'reserved_date' => 'date:Y-m-d',
            'party_size' => 'integer',
            'confirmed_at' => 'immutable_datetime',
            'terminal_at' => 'immutable_datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * @return HasMany<RequestMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(RequestMessage::class, 'request_id');
    }

    /**
     * @return HasMany<RequestEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(RequestEvent::class, 'request_id');
    }

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    /**
     * HH:MM slot of a reservation (restaurant timezone), if any.
     */
    public function slotLabel(): ?string
    {
        return $this->reserved_slot;
    }
}
