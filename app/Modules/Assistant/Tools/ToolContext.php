<?php

namespace App\Modules\Assistant\Tools;

use App\Models\AiTurn;
use App\Models\Conversation;

/**
 * Turn data a tool may use: the turn (for per-turn idempotency), its
 * conversation, the active locale, and the guest's validated preferences
 * (merged into menu searches by the server so the model cannot drop them).
 */
final class ToolContext
{
    /**
     * @param  array{dietary_tags: list<string>, exclude_allergens: list<string>, party_size: ?int}|null  $preferences
     */
    public function __construct(
        public readonly AiTurn $turn,
        public readonly Conversation $conversation,
        public readonly string $locale,
        public readonly ?array $preferences = null,
    ) {}
}
