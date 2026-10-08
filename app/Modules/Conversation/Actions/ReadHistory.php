<?php

namespace App\Modules\Conversation\Actions;

use App\Models\AiTurn;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Modules\Conversation\Enums\TurnStatus;
use App\Modules\Conversation\Support\EvidenceAvailability;
use App\Modules\Conversation\Support\TurnPresenter;

/**
 * Safe history: cursor by message id (ascending, max 50). Answers whose
 * sources are gone are replaced by a standard notice; answers whose sources
 * changed (price, availability, a replaced revision) are flagged stale.
 */
class ReadHistory
{
    public const PAGE_SIZE = 50;

    /**
     * @return array{messages: list<array<string, mixed>>, next_after_id: ?int}
     */
    public function handle(Conversation $conversation, int $afterId = 0): array
    {
        $messages = ConversationMessage::query()
            ->with('turn:id,public_id')
            ->where('conversation_id', $conversation->id)
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit(self::PAGE_SIZE)
            ->get();

        $availability = EvidenceAvailability::forTurns(
            $messages->where('role', 'assistant')->pluck('turn_id')->filter()->unique()->values()->all(),
        );

        return [
            'messages' => $messages->map(fn (ConversationMessage $message) => TurnPresenter::message(
                $message,
                (int) $conversation->guest_session_id,
                $message->role === 'assistant' ? ($availability[$message->turn_id] ?? 'ok') : 'ok',
            ))->all(),
            'next_after_id' => $messages->count() === self::PAGE_SIZE ? $messages->last()->id : null,
        ];
    }

    /**
     * History for the prompt: completed turns only, oldest first, without
     * turns whose sources are stale or no longer available.
     *
     * @return list<array{role: string, text: string}>
     */
    public function forPrompt(Conversation $conversation, int $excludeTurnId): array
    {
        $turns = AiTurn::query()
            ->where('conversation_id', $conversation->id)
            ->where('status', TurnStatus::Completed->value)
            ->where('id', '!=', $excludeTurnId)
            ->orderByDesc('id')
            ->limit((int) config('assistant.history.max_messages'))
            ->pluck('id')
            ->all();

        $availability = EvidenceAvailability::forTurns($turns);
        $allowed = array_values(array_filter($turns, fn (int $id) => ($availability[$id] ?? 'ok') === 'ok'));

        return ConversationMessage::query()
            ->whereIn('turn_id', $allowed)
            ->orderBy('id')
            ->get(['role', 'text'])
            ->map(fn (ConversationMessage $message) => ['role' => $message->role, 'text' => $message->text])
            ->all();
    }
}
