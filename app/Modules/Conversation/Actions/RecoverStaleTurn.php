<?php

namespace App\Modules\Conversation\Actions;

use App\Models\AiTurn;
use App\Models\Conversation;
use App\Modules\Conversation\Enums\TurnStatus;
use App\Modules\Conversation\Support\TurnRecorder;

/**
 * Marks turns still "processing" after the lease as failed
 * (turn_interrupted) with a fallback message. The original process cannot
 * commit afterwards because TurnRecorder only finishes processing turns.
 * Plan 8 also runs this from the scheduler.
 */
class RecoverStaleTurn
{
    public function forConversation(Conversation $conversation): int
    {
        $stale = AiTurn::query()
            ->where('conversation_id', $conversation->id)
            ->where('status', TurnStatus::Processing->value)
            ->where('started_at', '<', now()->subSeconds((int) config('assistant.lease_seconds')))
            ->get();

        $count = 0;
        foreach ($stale as $turn) {
            $count += (int) $this->recover($turn, 'turn_interrupted');
        }

        return $count;
    }

    public function ifStale(AiTurn $turn): bool
    {
        if ($turn->status !== TurnStatus::Processing
            || $turn->started_at->greaterThan(now()->subSeconds((int) config('assistant.lease_seconds')))) {
            return false;
        }

        return $this->recover($turn, 'turn_interrupted');
    }

    public function recover(AiTurn $turn, string $code): bool
    {
        return TurnRecorder::finish(
            $turn,
            TurnStatus::Failed,
            __('deck.assistant.interrupted', [], $turn->locale),
            ['sources' => [], 'ui' => [], 'actions' => []],
            null,
            errorCode: $code,
        );
    }
}
