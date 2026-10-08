<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Plain-text chat message. `ui` holds server-built card descriptors only.
 */
#[Fillable(['conversation_id', 'turn_id', 'role', 'text', 'ui', 'created_at'])]
class ConversationMessage extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'ui' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * @return BelongsTo<AiTurn, $this>
     */
    public function turn(): BelongsTo
    {
        return $this->belongsTo(AiTurn::class, 'turn_id');
    }
}
