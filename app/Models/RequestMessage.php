<?php

namespace App\Models;

use App\Modules\Requests\Enums\MessageVisibility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Plain-text message on a request. `internal` messages are staff notes and
 * are never shown to guests or sent to the model.
 */
#[Fillable(['request_id', 'author_type', 'author_user_id', 'visibility', 'text', 'created_at'])]
class RequestMessage extends Model
{
    public const UPDATED_AT = null;

    public const AUTHOR_GUEST = 'guest';

    public const AUTHOR_STAFF = 'staff';

    protected function casts(): array
    {
        return [
            'visibility' => MessageVisibility::class,
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
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }
}
