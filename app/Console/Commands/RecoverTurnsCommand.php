<?php

namespace App\Console\Commands;

use App\Models\AiTurn;
use App\Modules\Conversation\Actions\RecoverStaleTurn;
use App\Modules\Conversation\Enums\TurnStatus;
use App\Modules\Operations\Support\Heartbeat;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Marks turns still processing after the 75 s lease as failed
 * turn_interrupted with one fallback message (TurnRecorder finishes a turn
 * at most once). Leases are not force-released: their cache locks carry the
 * same 75 s TTL, so the old owner's lock has already expired and a newer
 * turn's lease must not be touched. Scheduled every minute; also records the
 * scheduler heartbeat for readiness.
 */
#[Signature('restaurant:recover-turns')]
#[Description('Fail AI turns that are still processing after the lease expired')]
class RecoverTurnsCommand extends Command
{
    public function handle(RecoverStaleTurn $recover): int
    {
        Heartbeat::beat(Heartbeat::SCHEDULER);

        $recovered = 0;
        AiTurn::query()
            ->where('status', TurnStatus::Processing->value)
            ->where('started_at', '<', now()->subSeconds((int) config('assistant.lease_seconds')))
            ->orderBy('id')
            ->limit(500)
            ->get()
            ->each(function (AiTurn $turn) use ($recover, &$recovered) {
                $recovered += (int) $recover->recover($turn, 'turn_interrupted');
            });

        $this->components->info("Recovered {$recovered} interrupted turn(s).");

        return self::SUCCESS;
    }
}
