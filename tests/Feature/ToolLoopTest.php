<?php

namespace Tests\Feature;

use App\Models\AiTurn;
use App\Models\GuestRequest;
use App\Models\RequestDraft;
use App\Models\ToolInvocation;
use App\Modules\Assistant\Gateway\ChatCompletionResult;
use App\Modules\Assistant\Gateway\GatewayException;
use App\Modules\Requests\Presenters\RequestCards;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\Support\BuildsRestaurantData;
use Tests\Support\TalksToAssistant;
use Tests\TestCase;

/**
 * Plan 7: the tool loop in ExecuteTurn (protocol, limits, terminal results,
 * failures after a draft was saved) and the server-side preference merge.
 */
class ToolLoopTest extends TestCase
{
    use BuildsRestaurantData;
    use RefreshDatabase;
    use TalksToAssistant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();
        $this->freezeClock();
        $this->toolsEnabled(true);
        $this->withoutMiddleware(ThrottleRequests::class);
        RateLimiter::clear('guest-session-create');
    }

    public function test_a_menu_tool_result_feeds_the_final_answer_with_the_right_protocol(): void
    {
        $dish = $this->dish('rendang', 68000, [], ['halal']);
        $fake = $this->fakeModel([
            $this->toolCall('search_menu', ['query' => 'rendang'], 'call_abc'),
            $this->finalAnswer('Rendang sapi tersedia.', [$dish->id]),
        ]);

        $response = $this->say($this->startConversation(), 'ada rendang?')->assertOk();

        $this->assertSame('completed', $response->json('data.status'));
        $this->assertSame($dish->id, $this->cardsOf($response, 'menu_item_card')['item_id']);
        $this->assertCount(2, $fake->requests);
        $this->assertNotEmpty($fake->requests[0]->tools, 'The first request carries the tool definitions.');

        $second = $fake->requests[1]->messages;
        $assistant = collect($second)->firstWhere('role', 'assistant');
        $tool = collect($second)->firstWhere('role', 'tool');
        $this->assertSame('call_abc', $assistant['tool_calls'][0]['id']);
        $this->assertSame('call_abc', $tool['tool_call_id'], 'The tool message answers the call by id.');
        $this->assertSame('ok', json_decode($tool['content'], true)['status']);
        $this->assertSame(['search_menu:ok'], ToolInvocation::query()->get()->map(fn ($i) => "{$i->name}:{$i->status}")->all());
    }

    public function test_a_dish_found_only_by_a_tool_may_be_cited_but_an_unseen_id_may_not(): void
    {
        $dish = $this->dish('rendang', 68000, options: ['names' => ['id' => 'Rendang', 'en' => 'Rendang', 'ja' => 'ルンダン']]);
        $other = $this->dish('es-teh', 12000, options: ['names' => ['id' => 'Es teh', 'en' => 'Iced tea', 'ja' => 'お茶']]);

        $this->fakeModel([$this->toolCall('get_menu_item', ['item_id' => $dish->id]), $this->finalAnswer('Rendang.', [$other->id])]);
        $response = $this->say($this->startConversation('welcome'), 'ceritakan hidangan nomor satu')->assertOk();

        $this->assertSame('failed', $response->json('data.status'));
        $this->assertSame('ai_invalid_source', $response->json('data.error_code'));
    }

    public function test_terminal_results_are_written_by_the_server_without_another_completion(): void
    {
        [$date] = $this->openDay();
        $fake = $this->fakeModel([$this->toolCall('check_availability', ['date' => $date, 'party_size' => 4])]);

        $response = $this->say($this->startConversation('reservation'), 'ada meja untuk 4 orang?', ['scene' => 'reservation'])->assertOk();

        $this->assertCount(1, $fake->requests);
        $this->assertSame('completed', $response->json('data.status'));
        $this->assertNotEmpty($this->cardsOf($response, 'availability_slots')['slots']);
        $this->assertStringContainsString('4', $response->json('data.message.text'));
        $this->assertSame('open_scene', $response->json('data.actions.0.type'));
        $this->assertStringStartsWith('/reservation?date='.$date, $response->json('data.actions.0.path'));
    }

    public function test_an_unknown_tool_is_reported_to_the_model_which_can_still_answer(): void
    {
        $dish = $this->dish('rendang', 68000);
        $fake = $this->fakeModel([$this->toolCall('delete_everything', []), $this->finalAnswer('Rendang.', [$dish->id])]);

        $response = $this->say($this->startConversation(), 'rendang')->assertOk();

        $this->assertSame('completed', $response->json('data.status'));
        $tool = collect($fake->requests[1]->messages)->firstWhere('role', 'tool');
        $this->assertSame('unknown_tool', json_decode($tool['content'], true)['status']);
        $this->assertSame('unknown_tool', ToolInvocation::query()->firstOrFail()->status);
    }

    public function test_invalid_arguments_come_back_as_data_for_the_model_to_correct(): void
    {
        $dish = $this->dish('rendang', 68000);
        $fake = $this->fakeModel([
            $this->toolCall('search_menu', ['limit' => 500]),
            $this->toolCall('search_menu', ['query' => 'rendang'], 'call_2'),
            $this->finalAnswer('Rendang.', [$dish->id]),
        ]);

        $this->say($this->startConversation(), 'rendang')->assertOk()->assertJsonPath('data.status', 'completed');

        $first = json_decode(collect($fake->requests[1]->messages)->firstWhere('role', 'tool')['content'], true);
        $this->assertSame('validation_failed', $first['status']);
        $this->assertArrayHasKey('limit', $first['fields']);
        $this->assertSame(['validation_failed', 'ok'], ToolInvocation::query()->orderBy('id')->pluck('status')->all());
    }

    public function test_the_round_limit_ends_the_turn_with_a_fallback(): void
    {
        $this->dish('rendang', 68000);
        $fake = $this->fakeModel(array_map(fn ($i) => $this->toolCall('search_menu', ['query' => "rendang {$i}"], "call_{$i}"), range(1, 10)));

        $response = $this->say($this->startConversation(), 'rendang')->assertOk();

        $this->assertSame('failed', $response->json('data.status'));
        $this->assertSame('tool_limit_exceeded', $response->json('data.error_code'));
        $this->assertLessThanOrEqual((int) config('assistant.max_completion_calls'), count($fake->requests));
        $this->assertLessThanOrEqual((int) config('assistant.max_tool_rounds') + 1, count($fake->requests));
    }

    public function test_more_than_five_calls_in_one_response_are_not_all_executed(): void
    {
        $this->dish('rendang', 68000);
        $calls = array_map(fn ($i) => ['id' => "c{$i}", 'name' => 'search_menu', 'arguments' => '{"query":"rendang"}'], range(1, 7));
        $this->fakeModel([new ChatCompletionResult(null, $calls, 'tool_calls', [], 'fake')]);

        $response = $this->say($this->startConversation(), 'rendang')->assertOk();

        $this->assertSame('tool_limit_exceeded', $response->json('data.error_code'));
        $this->assertSame(5, ToolInvocation::query()->count());
    }

    public function test_a_provider_without_function_calling_reports_the_capability_gap(): void
    {
        $this->dish('rendang', 68000);
        $this->fakeModel([new GatewayException(GatewayException::BAD_REQUEST)]);

        $response = $this->say($this->startConversation(), 'rendang')->assertOk();

        $this->assertSame('tool_capability_unavailable', $response->json('data.error_code'));
        $this->assertSame(__('deck.assistant.tools.tool_capability_unavailable', [], 'id'), $response->json('data.message.text'));
        $this->get('/menu')->assertOk();
    }

    public function test_a_tool_call_is_refused_when_tools_are_off(): void
    {
        $this->toolsEnabled(false);
        $this->dish('rendang', 68000);
        $this->fakeModel([$this->toolCall('search_menu', ['query' => 'rendang'])]);

        $response = $this->say($this->startConversation(), 'rendang')->assertOk();

        $this->assertSame('ai_unexpected_tool_call', $response->json('data.error_code'));
        $this->assertSame(0, ToolInvocation::query()->count());
    }

    public function test_a_failure_after_a_draft_was_saved_still_returns_the_draft_and_never_prepares_twice(): void
    {
        [$date, $slot] = $this->openDay();
        $fake = $this->fakeModel([
            $this->toolCall('search_menu', ['query' => 'apa saja']),
            $this->toolCall('prepare_reservation', ['date' => $date, 'slot' => $slot, 'party_size' => 2], 'call_2'),
        ]);
        $this->dish('rendang', 68000);

        // Round 1 is a non-terminal search, round 2 saves the draft and ends the turn.
        $response = $this->say($this->startConversation(), 'reservasi untuk 2 orang')->assertOk();

        $this->assertCount(2, $fake->requests);
        $this->assertSame('completed', $response->json('data.status'));
        $this->assertSame('request_draft', $this->cardsOf($response, 'request_draft')['type']);
        $this->assertSame(1, RequestDraft::query()->count());
        $this->assertSame('draft', $response->json('data.operation.type'));
    }

    public function test_a_draft_saved_before_a_provider_error_is_still_offered_for_review(): void
    {
        [$date, $slot] = $this->openDay();
        $this->dish('rendang', 68000);
        $this->fakeModel([
            // Two tool calls in one response: the draft is saved, then the second call is over the limit? No: both run; the
            // second round fails at the provider and must not lose the draft.
            new ChatCompletionResult(null, [
                ['id' => 'a', 'name' => 'prepare_reservation', 'arguments' => (string) json_encode(['date' => $date, 'slot' => $slot, 'party_size' => 2])],
            ], 'tool_calls', [], 'fake'),
        ]);

        $first = $this->say($this->startConversation(), 'tolong buatkan reservasi')->assertOk();
        $this->assertSame(1, RequestDraft::query()->count());
        $this->assertSame('request_draft', $this->cardsOf($first, 'request_draft')['type']);

        // The failure path itself: a draft exists for the turn and the provider then fails.
        $turn = AiTurn::query()->firstOrFail();
        $cards = RequestCards::draftCardsForTurn($turn);
        $this->assertSame('request_draft', $cards['ui'][0]['type']);
        $this->assertSame('review_request_draft', $cards['actions'][0]['type']);
    }

    public function test_one_draft_per_turn_a_second_different_draft_conflicts_and_the_same_one_is_reused(): void
    {
        [$date, $slot] = $this->openDay();
        $args = ['date' => $date, 'slot' => $slot, 'party_size' => 2];
        $other = ['date' => $date, 'slot' => $slot, 'party_size' => 6];
        $calls = [
            ['id' => 'a', 'name' => 'prepare_reservation', 'arguments' => (string) json_encode($args)],
            ['id' => 'b', 'name' => 'prepare_reservation', 'arguments' => (string) json_encode($args)],
            ['id' => 'c', 'name' => 'prepare_reservation', 'arguments' => (string) json_encode($other)],
        ];
        $this->fakeModel([new ChatCompletionResult(null, $calls, 'tool_calls', [], 'fake')]);

        $response = $this->say($this->startConversation(), 'tolong buatkan reservasi')->assertOk();

        $this->assertSame(1, RequestDraft::query()->count());
        $this->assertSame(2, RequestDraft::query()->firstOrFail()->payload['party_size']);
        $statuses = ToolInvocation::query()->orderBy('id')->pluck('status')->all();
        $this->assertSame(['ok', 'ok', 'conflict'], $statuses);
        $this->assertSame('completed', $response->json('data.status'));
    }

    public function test_replaying_the_turn_does_not_create_a_second_draft(): void
    {
        [$date, $slot] = $this->openDay();
        $fake = $this->fakeModel([$this->toolCall('prepare_reservation', ['date' => $date, 'slot' => $slot, 'party_size' => 2])]);
        $conversation = $this->startConversation();
        $key = (string) Str::uuid();

        $first = $this->say($conversation, 'tolong buatkan reservasi', key: $key)->assertOk();
        $second = $this->say($conversation, 'tolong buatkan reservasi', key: $key)->assertOk();

        $this->assertSame($first->json('data.turn_id'), $second->json('data.turn_id'));
        $this->assertSame(1, RequestDraft::query()->count());
        $this->assertCount(1, $fake->requests);
    }

    public function test_saying_yes_in_the_chat_never_creates_a_request(): void
    {
        [$date, $slot] = $this->openDay();
        $this->fakeModel([
            $this->toolCall('prepare_reservation', ['date' => $date, 'slot' => $slot, 'party_size' => 2]),
            $this->finalAnswer('Baik, reservasi Anda sudah dikonfirmasi!', [1]),
        ]);
        $this->dish('rendang', 68000);
        $conversation = $this->startConversation();

        $this->say($conversation, 'reservasi untuk 2 orang')->assertOk();
        $this->say($conversation, 'ya, konfirmasi sekarang')->assertOk();

        $this->assertSame(0, GuestRequest::query()->count());
        $this->assertSame(1, RequestDraft::query()->whereNull('consumed_request_id')->count());
        $this->assertDatabaseCount('reservation_slot_usage', 0);
    }

    // ----- preferences -------------------------------------------------------

    public function test_the_server_merges_saved_preferences_into_search_menu(): void
    {
        $this->dish('gado-gado', 32000, ['peanuts' => 'contains'], ['vegetarian']);
        $this->dish('sate', 42000, ['peanuts' => 'may_contain', 'soy' => 'contains'], ['halal']);
        $safe = $this->dish('gulai', 36000, [], ['vegetarian']);
        $this->unverifiedDish('soto', 38000, ['celery' => 'contains'], ['vegetarian']);
        // The model asks for everything and drops the guest's filters.
        $fake = $this->fakeModel([$this->toolCall('search_menu', ['query' => 'a', 'exclude_allergens' => [], 'dietary_tags' => []]), $this->finalAnswer('Gulai.', [$safe->id])]);

        $this->say($this->startConversation('picks'), 'rekomendasi untukku', ['scene' => 'picks', 'preferences' => ['dietary_tags' => ['vegetarian'], 'exclude_allergens' => ['peanuts'], 'party_size' => null]])->assertOk();

        $tool = json_decode(collect($fake->requests[1]->messages)->firstWhere('role', 'tool')['content'], true);
        $this->assertSame([$safe->id], array_column($tool['dishes'], 'item_id'));
        $this->assertSame(['peanuts'], $tool['applied_filters']['exclude_allergens']);
        $this->assertSame(['vegetarian'], $tool['applied_filters']['dietary_tags']);
        $this->assertSame(1, $tool['unverified_dishes_withheld']);
    }

    public function test_model_filters_add_to_the_preferences_but_never_replace_them(): void
    {
        $this->dish('gado-gado', 32000, ['peanuts' => 'contains']);
        $this->dish('lumpia', 26000, ['cereals_gluten' => 'contains']);
        $fine = $this->dish('es-teh', 12000, []);
        $fake = $this->fakeModel([$this->toolCall('search_menu', ['query' => 'e', 'exclude_allergens' => ['cereals_gluten']]), $this->finalAnswer('Es teh.', [$fine->id])]);

        $this->say($this->startConversation(), 'apa saja', ['preferences' => ['dietary_tags' => [], 'exclude_allergens' => ['peanuts'], 'party_size' => null]])->assertOk();

        $tool = json_decode(collect($fake->requests[1]->messages)->firstWhere('role', 'tool')['content'], true);
        $this->assertSame(['cereals_gluten', 'peanuts'], $tool['applied_filters']['exclude_allergens']);
        $this->assertSame([$fine->id], array_column($tool['dishes'], 'item_id'));
    }

    public function test_preferences_outside_the_whitelist_are_refused_before_any_model_call(): void
    {
        $fake = $this->fakeModel([]);

        $this->say($this->startConversation(), 'halo', ['preferences' => ['exclude_allergens' => ['everything']]])
            ->assertStatus(422)->assertJsonPath('error.code', 'invalid_context');
        $this->assertCount(0, $fake->requests);
    }
}
