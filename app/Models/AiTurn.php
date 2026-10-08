<?php

namespace App\Models;

use App\Modules\Conversation\Enums\TurnStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'public_id', 'conversation_id', 'client_turn_id', 'request_fingerprint', 'status', 'locale',
    'context_snapshot', 'started_at', 'finished_at', 'deadline_at', 'error_code', 'provider',
    'model', 'prompt_version', 'usage', 'outcome',
])]
class AiTurn extends Model
{
    protected function casts(): array
    {
        return [
            'status' => TurnStatus::class,
            'context_snapshot' => 'array',
            'usage' => 'array',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
            'deadline_at' => 'immutable_datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * @return HasMany<ConversationMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class, 'turn_id');
    }

    /**
     * @return HasMany<TurnEvidence, $this>
     */
    public function evidence(): HasMany
    {
        return $this->hasMany(TurnEvidence::class, 'turn_id');
    }

    /**
     * @return HasMany<ToolInvocation, $this>
     */
    public function toolInvocations(): HasMany
    {
        return $this->hasMany(ToolInvocation::class, 'turn_id');
    }

    public function isTerminal(): bool
    {
        return $this->status !== TurnStatus::Processing;
    }
}
