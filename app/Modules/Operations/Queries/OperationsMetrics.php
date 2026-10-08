<?php

namespace App\Modules\Operations\Queries;

use App\Models\AiTurn;
use App\Models\GuestRequest;
use App\Models\ToolInvocation;
use App\Modules\Assistant\Gateway\GatewayException;
use App\Modules\Conversation\Enums\TurnStatus;
use App\Modules\Conversation\Support\AssistantBudget;
use App\Modules\Operations\Support\RateLimitHits;
use App\Modules\Requests\Enums\RequestStatus;
use App\Modules\Requests\Enums\RequestType;
use App\Modules\Requests\Support\OpeningSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Admin metrics (counts and latencies only; never request, contact, or
 * message content) plus the pilot alert rules from PLAN_08:
 *  - AI error rate > 10% of at least 20 turns in the last hour,
 *  - p95 turn latency > 55 s over at least 20 turns in the last hour,
 *  - a pending request unhandled for more than 120 minutes (configurable)
 *    while the restaurant is open,
 *  - the global AI budget at 80% or more.
 */
class OperationsMetrics
{
    public const WINDOW_DAYS = 7;

    public const ALERT_MIN_TURNS = 20;

    public const ALERT_ERROR_RATE = 0.10;

    public const ALERT_P95_MS = 55000;

    public const ALERT_BUDGET_SHARE = 0.80;

    /** Outcomes that mean retrieval found nothing to ground an answer. */
    public const NO_MATCH_OUTCOMES = ['no_source', 'model_no_source'];

    public function __construct(private readonly OpeningSchedule $schedule) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $since = $now->subDays(self::WINDOW_DAYS);

        $turns = $this->turnStats($since);
        $lastHour = $this->turnStats($now->subHour());

        $pendingMinutes = (int) config('restaurant.operations.pending_alert_minutes');
        $oldestPending = GuestRequest::query()->where('status', RequestStatus::Pending->value)->min('confirmed_at');
        $pendingAge = $oldestPending === null ? null : (int) CarbonImmutable::parse($oldestPending)->diffInMinutes($now, true);

        $cap = max(1, (int) config('assistant.daily_turn_cap'));
        $budgetUsed = AssistantBudget::globalToday();

        return [
            'window_days' => self::WINDOW_DAYS,
            'turns' => $turns + ['budget_exhausted' => $this->budgetExhausted($since)],
            'rate_limited' => RateLimitHits::lastDays(self::WINDOW_DAYS),
            'tool_validation_failures' => ToolInvocation::query()
                ->where('started_at', '>=', $since)
                ->where('status', 'validation_failed')
                ->count(),
            'requests' => [
                'by_type_status' => $this->requestsByTypeAndStatus($since),
                'pending' => GuestRequest::query()->where('status', RequestStatus::Pending->value)->count(),
                'oldest_pending_minutes' => $pendingAge,
            ],
            'seats_today' => $this->seatsToday(),
            'budget' => ['used' => $budgetUsed, 'cap' => $cap, 'share' => round($budgetUsed / $cap, 4)],
            'alerts' => [
                'error_rate' => $lastHour['total'] >= self::ALERT_MIN_TURNS && $lastHour['error_rate'] > self::ALERT_ERROR_RATE,
                'latency_p95' => $lastHour['total'] >= self::ALERT_MIN_TURNS && ($lastHour['p95_ms'] ?? 0) > self::ALERT_P95_MS,
                'pending_unhandled' => $pendingAge !== null && $pendingAge > $pendingMinutes && $this->isOpenNow($now),
                'ai_budget' => $budgetUsed / $cap >= self::ALERT_BUDGET_SHARE,
            ],
            'last_hour' => $lastHour,
        ];
    }

    /**
     * @return array{total: int, completed: int, failed: int, error_rate: float, timeouts: int, no_match: int, p50_ms: ?int, p95_ms: ?int}
     */
    private function turnStats(CarbonImmutable $since): array
    {
        $rows = AiTurn::query()
            ->where('started_at', '>=', $since)
            ->whereIn('status', [TurnStatus::Completed->value, TurnStatus::Failed->value])
            ->get(['status', 'error_code', 'outcome', 'started_at', 'finished_at']);

        // A turn answered with the fixed budget message is not an AI failure.
        $failed = $rows->filter(fn (AiTurn $turn) => $turn->status === TurnStatus::Failed && $turn->error_code !== 'ai_budget_exhausted')->count();
        $durations = $rows
            ->filter(fn (AiTurn $turn) => $turn->finished_at !== null)
            ->map(fn (AiTurn $turn) => (int) $turn->started_at->diffInMilliseconds($turn->finished_at))
            ->sort()
            ->values()
            ->all();

        return [
            'total' => $rows->count(),
            'completed' => $rows->count() - $failed,
            'failed' => $failed,
            'error_rate' => $rows->isEmpty() ? 0.0 : round($failed / $rows->count(), 4),
            'timeouts' => $rows->whereIn('error_code', [GatewayException::TIMEOUT, 'turn_interrupted'])->count(),
            'no_match' => $rows->whereIn('outcome', self::NO_MATCH_OUTCOMES)->count(),
            'p50_ms' => self::percentile($durations, 0.50),
            'p95_ms' => self::percentile($durations, 0.95),
        ];
    }

    private function budgetExhausted(CarbonImmutable $since): int
    {
        return AiTurn::query()->where('started_at', '>=', $since)->where('error_code', 'ai_budget_exhausted')->count();
    }

    /**
     * @return array<string, array<string, int>> type => status => count (requests confirmed in the window)
     */
    private function requestsByTypeAndStatus(CarbonImmutable $since): array
    {
        $result = [];
        foreach (RequestType::cases() as $type) {
            $result[$type->value] = array_fill_keys(RequestStatus::values(), 0);
        }

        GuestRequest::query()
            ->select('type', 'status', DB::raw('count(*) as total'))
            ->where('confirmed_at', '>=', $since)
            ->groupBy('type', 'status')
            ->get()
            ->each(function ($row) use (&$result) {
                // type and status are cast to enums on the model.
                $type = $row->type instanceof \BackedEnum ? $row->type->value : $row->type;
                $status = $row->status instanceof \BackedEnum ? $row->status->value : $row->status;

                if (isset($result[$type][$status])) {
                    $result[$type][$status] = (int) $row->total;
                }
            });

        return $result;
    }

    /**
     * Seats held today against the capacity of today's slots.
     *
     * @return array{booked: int, capacity: int, slots: int}
     */
    private function seatsToday(): array
    {
        $today = $this->schedule->today();
        $slots = count($this->schedule->slotsFor($today));
        $perSlot = $this->schedule->setting()->slotCapacity();

        return [
            'booked' => (int) DB::table('reservation_slot_usage')->where('date', $today)->sum('booked_seats'),
            'capacity' => $slots * $perSlot,
            'slots' => $slots,
        ];
    }

    private function isOpenNow(CarbonImmutable $now): bool
    {
        $local = $now->setTimezone(config('restaurant.timezone'));

        return $this->schedule->isWithinHours($local->format('Y-m-d'), $local->format('H:i'));
    }

    /**
     * Nearest-rank percentile of sorted values.
     *
     * @param  list<int>  $sorted
     */
    public static function percentile(array $sorted, float $p): ?int
    {
        if ($sorted === []) {
            return null;
        }

        $rank = (int) ceil($p * count($sorted));

        return $sorted[max(0, min(count($sorted) - 1, $rank - 1))];
    }
}
