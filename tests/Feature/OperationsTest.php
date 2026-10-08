<?php

namespace Tests\Feature;

use App\Models\AiTurn;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\ToolInvocation;
use App\Modules\Conversation\Actions\StartConversation;
use App\Modules\Conversation\Enums\TurnStatus;
use App\Modules\Experience\Enums\SceneCode;
use App\Modules\Operations\Queries\OperationsMetrics;
use App\Modules\Operations\Support\Heartbeat;
use App\Modules\Operations\Support\RateLimitHits;
use App\Modules\Operations\Support\Readiness;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\BuildsRestaurantData;
use Tests\TestCase;

/**
 * Plan 8: operations metrics (counts only), pilot alerts, readiness, and the
 * turn recovery command.
 */
class OperationsTest extends TestCase
{
    use BuildsRestaurantData;
    use RefreshDatabase;

    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();
        $this->freezeClock('2026-10-07 14:00:00');
        Cache::flush();
        [, $guest] = $this->makeGuest();
        $this->conversation = app(StartConversation::class)->handle($guest, 'id', SceneCode::Menu);
    }

    private function turn(string $status, ?string $error = null, int $seconds = 5, ?string $outcome = null, string $startedAgo = '10 minutes'): AiTurn
    {
        $started = now()->sub($startedAgo);

        return AiTurn::query()->create([
            'public_id' => (string) Str::uuid(), 'conversation_id' => $this->conversation->id, 'client_turn_id' => (string) Str::uuid(),
            'request_fingerprint' => str_repeat('c', 64), 'status' => $status, 'locale' => 'id',
            'context_snapshot' => ['scene' => 'menu', 'menu_item_id' => null, 'request_public_id' => null, 'preferences' => null],
            'started_at' => $started, 'finished_at' => $started->copy()->addSeconds($seconds), 'deadline_at' => $started->copy()->addMinute(),
            'error_code' => $error, 'outcome' => $outcome, 'prompt_version' => 'restaurant-v2',
        ]);
    }

    public function test_turn_counts_latency_and_timeouts_over_seven_days(): void
    {
        foreach ([2, 4, 6, 8, 10] as $seconds) {
            $this->turn('completed', seconds: $seconds, outcome: 'answered');
        }
        $this->turn('failed', 'ai_timeout', 60);
        $this->turn('completed', outcome: 'no_source');
        $this->turn('completed', seconds: 1, startedAgo: '8 days'); // outside the window

        $metrics = app(OperationsMetrics::class)->handle()['turns'];

        $this->assertSame(7, $metrics['total']);
        $this->assertSame(6, $metrics['completed']);
        $this->assertSame(1, $metrics['failed']);
        $this->assertSame(1, $metrics['timeouts']);
        $this->assertSame(1, $metrics['no_match']);
        $this->assertSame(6000, $metrics['p50_ms']); // sorted 2,4,5,6,8,10,60 s: nearest rank 4
        $this->assertSame(60000, $metrics['p95_ms']);
    }

    public function test_the_budget_message_is_counted_separately_and_is_not_an_ai_failure(): void
    {
        $this->turn('failed', 'ai_budget_exhausted', 0);
        $this->turn('completed', outcome: 'answered');

        $turns = app(OperationsMetrics::class)->handle()['turns'];

        $this->assertSame(1, $turns['budget_exhausted']);
        $this->assertSame(0, $turns['failed']);
        $this->assertSame(0.0, $turns['error_rate']);
    }

    public function test_error_rate_and_latency_alerts_need_twenty_turns_in_the_last_hour(): void
    {
        foreach (range(1, 19) as $i) {
            $this->turn('failed', 'ai_unavailable', 70);
        }
        $this->assertSame(['error_rate' => false, 'latency_p95' => false], array_intersect_key(app(OperationsMetrics::class)->handle()['alerts'], array_flip(['error_rate', 'latency_p95'])));

        $this->turn('failed', 'ai_unavailable', 70);
        $alerts = app(OperationsMetrics::class)->handle()['alerts'];
        $this->assertTrue($alerts['error_rate']);
        $this->assertTrue($alerts['latency_p95']);
    }

    public function test_a_healthy_hour_raises_no_alert(): void
    {
        foreach (range(1, 25) as $i) {
            $this->turn('completed', seconds: 3, outcome: 'answered');
        }

        $this->assertSame([], array_keys(array_filter(app(OperationsMetrics::class)->handle()['alerts'])));
    }

    public function test_a_pending_request_waiting_too_long_alerts_only_while_the_restaurant_is_open(): void
    {
        [, $guest] = $this->makeGuest();
        $this->freezeClock('2026-10-07 12:00:00');
        $this->feedbackFor($guest);

        $this->freezeClock('2026-10-07 13:00:00');
        $this->assertFalse(app(OperationsMetrics::class)->handle()['alerts']['pending_unhandled'], 'One hour is within the limit.');

        $this->freezeClock('2026-10-07 14:30:00');
        $metrics = app(OperationsMetrics::class)->handle();
        $this->assertTrue($metrics['alerts']['pending_unhandled']);
        $this->assertSame(150, $metrics['requests']['oldest_pending_minutes']);

        $this->freezeClock('2026-10-07 23:30:00'); // closed
        $this->assertFalse(app(OperationsMetrics::class)->handle()['alerts']['pending_unhandled']);

        config(['restaurant.operations.pending_alert_minutes' => 300]);
        $this->freezeClock('2026-10-07 14:30:00');
        $this->assertFalse(app(OperationsMetrics::class)->handle()['alerts']['pending_unhandled'], 'The threshold is configurable.');
    }

    public function test_the_global_ai_budget_alerts_at_eighty_percent(): void
    {
        config(['assistant.daily_turn_cap' => 10]);
        DB::table('assistant_usage')->insert(['usage_date' => now(config('restaurant.timezone'))->toDateString(), 'guest_session_id' => null, 'turns' => 7]);
        $this->assertFalse(app(OperationsMetrics::class)->handle()['alerts']['ai_budget']);

        DB::table('assistant_usage')->update(['turns' => 8]);
        $metrics = app(OperationsMetrics::class)->handle();
        $this->assertTrue($metrics['alerts']['ai_budget']);
        $this->assertSame(0.8, $metrics['budget']['share']);
    }

    public function test_requests_per_type_and_status_seats_and_validation_failures_are_counted(): void
    {
        [, $guest] = $this->makeGuest();
        $this->reservationFor($guest, 6);
        $this->feedbackFor($guest);
        $turn = $this->turn('completed');
        ToolInvocation::query()->create(['turn_id' => $turn->id, 'call_id' => 'x', 'name' => 'search_menu', 'status' => 'validation_failed', 'started_at' => now(), 'finished_at' => now()]);
        ToolInvocation::query()->create(['turn_id' => $turn->id, 'call_id' => 'y', 'name' => 'search_menu', 'status' => 'ok', 'started_at' => now(), 'finished_at' => now()]);

        $metrics = app(OperationsMetrics::class)->handle();

        $this->assertSame(1, $metrics['requests']['by_type_status']['reservation']['pending']);
        $this->assertSame(1, $metrics['requests']['by_type_status']['feedback']['pending']);
        $this->assertSame(0, $metrics['requests']['by_type_status']['order']['pending']);
        $this->assertSame(2, $metrics['requests']['pending']);
        $this->assertSame(1, $metrics['tool_validation_failures']);
        $this->assertSame(0, $metrics['seats_today']['booked'], 'The reservation is tomorrow, not today.');
        $this->assertSame(20 * 24, $metrics['seats_today']['capacity']);
    }

    public function test_rate_limit_hits_are_counted_by_day_without_identity(): void
    {
        $this->assertSame(0, RateLimitHits::lastDays(7));

        $this->withoutMiddleware(ThrottleRequests::class);
        RateLimitHits::record();
        RateLimitHits::record();
        $this->freezeClock('2026-10-09 10:00:00');
        RateLimitHits::record();

        $this->assertSame(3, RateLimitHits::lastDays(7));
        $this->assertSame(1, RateLimitHits::lastDays(1));
        $this->assertSame(3, app(OperationsMetrics::class)->handle()['rate_limited']);
    }

    public function test_a_real_429_is_recorded(): void
    {
        for ($i = 0; $i < 11; $i++) {
            $this->postJson('/api/v1/requests/lookup', ['reference' => 'RST-20261007-ABCDEFGH', 'contact' => 'x@example.test']);
        }

        $this->assertSame(1, RateLimitHits::lastDays(1));
    }

    public function test_metrics_hold_only_numbers_never_request_content(): void
    {
        [, $guest] = $this->makeGuest();
        $this->feedbackFor($guest, 'RAHASIA-SUBJEK');

        $json = json_encode(app(OperationsMetrics::class)->handle());

        $this->assertStringNotContainsString('RAHASIA', $json);
        $this->assertStringNotContainsString('Tamu Uji', $json);
    }

    // ----- readiness and pages ------------------------------------------------

    public function test_readiness_requires_database_and_cache_and_never_the_ai(): void
    {
        $result = app(Readiness::class)->handle();

        $this->assertTrue($result['ready']);
        $this->assertSame('ok', $result['checks']['database']['status']);
        $this->assertSame('ok', $result['checks']['cache']['status']);
        $this->assertFalse($result['checks']['assistant']['required']);
        $this->assertContains($result['checks']['assistant']['status'], ['ok', 'unavailable']);
        $this->assertFalse($result['checks']['storage']['required']);
        $this->assertSame('not_required', $result['checks']['worker']['status']);
    }

    public function test_the_scheduler_heartbeat_turns_ok_and_goes_stale(): void
    {
        $this->assertSame('stale', app(Readiness::class)->handle()['checks']['scheduler']['status']);

        Heartbeat::beat(Heartbeat::SCHEDULER);
        $this->assertSame('ok', app(Readiness::class)->handle()['checks']['scheduler']['status']);

        $this->freezeClock('2026-10-07 14:10:00');
        $this->assertSame('stale', app(Readiness::class)->handle()['checks']['scheduler']['status']);
    }

    public function test_readiness_endpoint_and_operations_page_are_admin_only(): void
    {
        foreach (['/api/v1/admin/readiness'] as $path) {
            $this->getJson($path)->assertUnauthorized();
            $this->actingAs($this->editor())->getJson($path)->assertForbidden();
            $this->actingAs($this->staff())->getJson($path)->assertForbidden();
        }
        foreach ([$this->editor(), $this->staff()] as $user) {
            $this->actingAs($user)->get('/admin/operations')->assertForbidden();
        }

        $admin = $this->admin();
        $this->actingAs($admin)->getJson('/api/v1/admin/readiness')->assertOk()->assertJsonPath('data.ready', true);
        $this->actingAs($admin)->get('/admin/operations')->assertOk()->assertSee(__('ui.operations.title'));
    }

    public function test_the_operations_page_renders_in_every_language_without_request_content(): void
    {
        [, $guest] = $this->makeGuest();
        $this->feedbackFor($guest, 'RAHASIA-SUBJEK');
        $admin = $this->admin();

        foreach (['id', 'en', 'ja'] as $locale) {
            $this->actingAs($admin)->get("/admin/operations?lang={$locale}")->assertOk()->assertDontSee('RAHASIA')->assertDontSee('ui.operations.', false);
        }
    }

    public function test_readiness_fails_with_503_only_when_a_required_check_fails(): void
    {
        $admin = $this->admin();
        Cache::shouldReceive('put')->andThrow(new \RuntimeException('cache down'));
        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('forget')->andReturnTrue();

        $this->actingAs($admin)->getJson('/api/v1/admin/readiness')->assertStatus(503)->assertJsonPath('data.checks.cache.status', 'failed');
    }

    // ----- recovery -----------------------------------------------------------

    public function test_the_recover_command_fails_stale_turns_once_and_beats_the_heartbeat(): void
    {
        $stale = $this->turn('processing', startedAgo: '5 minutes');
        $fresh = $this->turn('processing', startedAgo: '10 seconds');

        $this->artisan('restaurant:recover-turns')->assertSuccessful();
        $this->artisan('restaurant:recover-turns')->assertSuccessful();

        $this->assertSame(TurnStatus::Failed, $stale->refresh()->status);
        $this->assertSame('turn_interrupted', $stale->error_code);
        $this->assertSame(1, ConversationMessage::query()->where('turn_id', $stale->id)->where('role', 'assistant')->count());
        $this->assertSame(TurnStatus::Processing, $fresh->refresh()->status);
        $this->assertNotNull(Heartbeat::last(Heartbeat::SCHEDULER));
    }

    public function test_the_scheduler_runs_recovery_every_minute_and_purge_at_two(): void
    {
        $events = collect(app(Schedule::class)->events());
        $recover = $events->first(fn ($event) => str_contains($event->command, 'restaurant:recover-turns'));
        $purge = $events->first(fn ($event) => str_contains($event->command, 'restaurant:purge-data'));

        $this->assertSame('* * * * *', $recover->expression);
        $this->assertSame('0 2 * * *', $purge->expression);
        $this->assertSame(config('restaurant.timezone'), $purge->timezone);
        $this->assertTrue($purge->onOneServer);
        $this->assertTrue($purge->withoutOverlapping);
    }
}
