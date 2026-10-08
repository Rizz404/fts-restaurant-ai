<?php

namespace App\Modules\Conversation\Support;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Daily AI turn budget for the public endpoint: turns per guest session
 * (ASSISTANT_GUEST_DAILY_TURNS) and a global cap (ASSISTANT_DAILY_TURN_CAP).
 *
 * consume() counts the turn that is starting, including turns that later
 * fail, so a retry loop cannot slip past the limit. When either limit is
 * exceeded the turn is answered with the ai_budget_exhausted fallback and
 * the model is not called. Call inside the turn-creating transaction.
 * The day is the calendar day in the restaurant timezone.
 */
final class AssistantBudget
{
    /**
     * @return bool true when this turn is over the budget
     */
    public static function consume(int $guestSessionId): bool
    {
        $date = now(config('restaurant.timezone'))->toDateString();

        $session = self::increment($date, $guestSessionId);
        $global = self::increment($date, null);

        return $session > (int) config('assistant.guest.daily_turns')
            || $global > (int) config('assistant.daily_turn_cap');
    }

    /**
     * Turns used today across all guests (for operations alerts).
     */
    public static function globalToday(): int
    {
        $date = now(config('restaurant.timezone'))->toDateString();

        return (int) DB::table('assistant_usage')->where('usage_date', $date)->whereNull('guest_session_id')->value('turns');
    }

    private static function increment(string $date, ?int $guestSessionId): int
    {
        $query = fn () => DB::table('assistant_usage')
            ->where('usage_date', $date)
            ->when($guestSessionId === null, fn ($q) => $q->whereNull('guest_session_id'), fn ($q) => $q->where('guest_session_id', $guestSessionId));

        $row = $query()->lockForUpdate()->first();

        if ($row === null) {
            try {
                // A savepoint, so a lost insert race does not abort the outer transaction.
                DB::transaction(fn () => DB::table('assistant_usage')->insert([
                    'usage_date' => $date,
                    'guest_session_id' => $guestSessionId,
                    'turns' => 1,
                ]));

                return 1;
            } catch (UniqueConstraintViolationException) {
                $row = $query()->lockForUpdate()->first();
            }
        }

        $turns = (int) $row->turns + 1;
        $query()->update(['turns' => $turns]);

        return $turns;
    }
}
