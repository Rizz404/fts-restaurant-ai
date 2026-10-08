<?php

namespace App\Modules\Conversation\Support;

use App\Models\AiTurn;
use App\Models\ConversationMessage;
use App\Modules\Conversation\Enums\TurnStatus;
use App\Modules\Requests\Presenters\RequestCards;

/**
 * Shapes turn and message data for the API (TECHNICAL_CONTRACTS turn data).
 * Dish cards and request cards are re-read from canonical data on output, so
 * old messages show current prices and a confirmed draft shows its request.
 */
final class TurnPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function turn(AiTurn $turn): array
    {
        /** @var ConversationMessage|null $answer */
        $answer = $turn->status === TurnStatus::Processing
            ? null
            : $turn->messages()->where('role', 'assistant')->orderByDesc('id')->first();
        $guestSessionId = (int) $turn->conversation->guest_session_id;
        $ui = self::refresh($answer?->ui ?? [], $guestSessionId);

        return [
            'turn_id' => $turn->public_id,
            'status' => $turn->status->value,
            'message' => $answer === null ? null : [
                'id' => $answer->id,
                'role' => 'assistant',
                'text' => $answer->text,
            ],
            'sources' => $ui['sources'] ?? [],
            'ui' => $ui['ui'] ?? [],
            'actions' => $ui['actions'] ?? [],
            'operation' => self::operation($turn, $ui),
            'error_code' => $turn->status === TurnStatus::Failed ? $turn->error_code : null,
        ];
    }

    /**
     * @param  'ok'|'stale'|'unavailable'  $availability
     * @return array<string, mixed>
     */
    public static function message(ConversationMessage $message, int $guestSessionId, string $availability = 'ok'): array
    {
        $ui = self::refresh($message->ui ?? [], $guestSessionId);
        $unavailable = $availability === 'unavailable';

        return [
            'id' => $message->id,
            'role' => $message->role,
            'text' => $unavailable ? __('deck.assistant.history_unavailable') : $message->text,
            'turn_id' => $message->turn?->public_id,
            'sources' => $unavailable ? [] : ($ui['sources'] ?? []),
            'ui' => $unavailable ? [] : ($ui['ui'] ?? []),
            'actions' => $unavailable ? [] : ($ui['actions'] ?? []),
            'stale' => $availability === 'stale',
            'unavailable' => $unavailable,
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $ui
     * @return array<string, mixed>
     */
    private static function refresh(array $ui, int $guestSessionId): array
    {
        return RequestCards::refresh(TurnCards::refresh($ui), $guestSessionId);
    }

    /**
     * Draft/request reference created by this turn. Falls back to the draft
     * row so the reference survives a turn that failed after saving it.
     *
     * @param  array<string, mixed>  $ui
     * @return array<string, mixed>|null
     */
    private static function operation(AiTurn $turn, array $ui): ?array
    {
        if ($turn->status === TurnStatus::Processing) {
            return null;
        }

        return RequestCards::operationForTurn($turn) ?? ($ui['operation'] ?? null);
    }
}
