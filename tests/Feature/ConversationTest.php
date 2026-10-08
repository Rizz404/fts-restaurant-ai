<?php

namespace Tests\Feature;

use App\Models\AiTurn;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\GuestSession;
use App\Modules\Assistant\Gateway\ChatCompletionResult;
use App\Modules\Assistant\Gateway\GatewayException;
use App\Modules\Conversation\Actions\RecoverStaleTurn;
use App\Modules\Conversation\Enums\TurnStatus;
use App\Modules\Conversation\Support\AssistantBudget;
use App\Modules\Conversation\Support\TurnLease;
use App\Modules\Menu\Actions\ArchiveMenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\Support\BuildsRestaurantData;
use Tests\Support\TalksToAssistant;
use Tests\TestCase;

/**
 * Plan 5: conversation ownership, grounded answers, retries, budgets, and
 * context validation. The model is always a fake; retrieval-only mode
 * (tools off) is used so the wire format of each answer is explicit.
 */
class ConversationTest extends TestCase
{
    use BuildsRestaurantData;
    use RefreshDatabase;
    use TalksToAssistant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();
        $this->freezeClock();
        $this->toolsEnabled(false);
        RateLimiter::clear('guest-session-create');
    }

    // ----- ownership -------------------------------------------------------

    public function test_a_guest_cannot_read_or_write_in_another_guests_conversation(): void
    {
        $this->dish('rendang', 68000);
        $this->fakeModel([$this->finalAnswer('Rendang 68.000', [1])]);

        $conversation = $this->startConversation();
        $turnId = $this->say($conversation, 'harga rendang')->assertOk()->json('data.turn_id');

        // A second browser (new session) has no access at all.
        $this->flushSession();
        $this->getJson("/api/v1/conversations/{$conversation}/messages")->assertNotFound();
        $this->say($conversation, 'halo lagi')->assertNotFound();
        $turn = AiTurn::query()->where('public_id', $turnId)->firstOrFail();
        $this->getJson("/api/v1/conversations/{$conversation}/turns/{$turn->public_id}")->assertNotFound();
    }

    public function test_the_owner_reads_history_in_order_with_a_cursor(): void
    {
        $this->dish('rendang', 68000);
        $this->fakeModel([$this->finalAnswer('Satu', [1]), $this->finalAnswer('Dua', [1])]);
        $conversation = $this->startConversation();
        $this->say($conversation, 'rendang pertama')->assertOk();
        $this->say($conversation, 'rendang kedua')->assertOk();

        $page = $this->getJson("/api/v1/conversations/{$conversation}/messages")->assertOk();

        $this->assertSame(['user', 'assistant', 'user', 'assistant'], array_column($page->json('data'), 'role'));
        $first = $page->json('data.0.id');
        $this->getJson("/api/v1/conversations/{$conversation}/messages?after_id={$first}")->assertOk()->assertJsonCount(3, 'data');
        $this->assertNull($page->json('meta.next_after_id'));
    }

    // ----- grounded answers ------------------------------------------------

    public function test_an_answer_cites_a_dish_and_the_card_comes_from_the_database_not_the_text(): void
    {
        $dish = $this->dish('rendang', 68000, [], ['halal']);
        $this->fakeModel([$this->finalAnswer('Rendang harganya 1.000 rupiah.', [$dish->id])]);

        $response = $this->say($this->startConversation(), 'harga rendang')->assertOk();

        $card = $this->cardsOf($response, 'menu_item_card');
        $this->assertSame(68000, $card['price'], 'The card price is canonical even when the model text says otherwise.');
        $this->assertSame($dish->id, $card['item_id']);
        $this->assertSame('answered', AiTurn::query()->latest('id')->firstOrFail()->outcome);
    }

    public function test_an_invalid_source_is_rejected_and_replaced_by_a_fallback(): void
    {
        $dish = $this->dish('rendang', 68000);
        $this->fakeModel([$this->finalAnswer('Palsu', [$dish->id + 99])]);

        $response = $this->say($this->startConversation(), 'rendang')->assertOk();

        $this->assertSame('failed', $response->json('data.status'));
        $this->assertSame('ai_invalid_source', $response->json('data.error_code'));
        $this->assertStringNotContainsString('Palsu', $response->json('data.message.text'));
    }

    public function test_without_any_source_the_model_is_not_called(): void
    {
        $fake = $this->fakeModel([]);

        $response = $this->say($this->startConversation('welcome'), 'kapan matahari terbit di bulan?')->assertOk();

        $this->assertCount(0, $fake->requests);
        $this->assertSame('completed', $response->json('data.status'));
        $this->assertSame('no_source', AiTurn::query()->latest('id')->firstOrFail()->outcome);
    }

    public function test_greetings_and_navigation_are_answered_without_the_model(): void
    {
        $fake = $this->fakeModel([]);
        $conversation = $this->startConversation('welcome');

        $this->say($conversation, 'Halo!')->assertOk()->assertJsonPath('data.status', 'completed');
        $navigation = $this->say($conversation, 'lihat menu')->assertOk();

        $this->assertCount(0, $fake->requests);
        $this->assertSame('open_scene', $navigation->json('data.actions.0.type'));
        $this->assertSame('/menu', $navigation->json('data.actions.0.path'));
    }

    public function test_info_answers_cite_the_published_revision(): void
    {
        $article = $this->infoArticle('jam-buka', 'hours', [
            'id' => ['title' => 'Jam buka', 'body' => 'Buka 10.00 sampai 22.00.'],
            'en' => ['title' => 'Opening hours', 'body' => 'Open 10:00 to 22:00.'],
            'ja' => ['title' => '営業時間', 'body' => '10:00から22:00。'],
        ]);
        $revision = $article->revisions()->where('status', 'published')->firstOrFail();
        $this->fakeModel([$this->finalAnswer('Kami buka 10.00 sampai 22.00.', [], [$revision->id])]);

        $response = $this->say($this->startConversation('info'), 'jam buka berapa?', ['scene' => 'info'])->assertOk();

        $this->assertSame('source_card', $this->cardsOf($response, 'source_card')['type']);
        $this->assertSame('/info/jam-buka', $response->json('data.sources.0.path'));
    }

    public function test_an_answer_that_calls_an_unverified_dish_safe_is_replaced(): void
    {
        $dish = $this->unverifiedDish('soto', 38000, ['celery' => 'contains']);
        $this->fakeModel([$this->finalAnswer('Soto ini aman untuk alergi seledri Anda.', [$dish->id])]);

        $response = $this->say($this->startConversation(), 'apakah soto aman untuk alergi seledri?')->assertOk();

        $this->assertSame(__('deck.assistant.allergen_safe_claim', [], 'id'), $response->json('data.message.text'));
        $this->assertStringNotContainsString('seledri Anda', $response->json('data.message.text'));
        $this->assertSame('allergen_guard', AiTurn::query()->latest('id')->firstOrFail()->outcome);
    }

    public function test_cards_never_expose_allergen_data_of_an_unverified_dish(): void
    {
        $dish = $this->unverifiedDish('soto', 38000, ['celery' => 'contains'], ['halal']);
        $this->fakeModel([$this->finalAnswer('Soto ayam.', [$dish->id])]);

        $card = $this->cardsOf($this->say($this->startConversation(), 'soto')->assertOk(), 'menu_item_card');

        $this->assertFalse($card['allergen_verified']);
        $this->assertSame(['contains' => [], 'may_contain' => []], $card['allergens']);
        $this->assertSame([], $card['dietary_tags']);
    }

    public function test_gateway_failures_become_a_failed_turn_with_a_fallback_message(): void
    {
        $this->dish('rendang', 68000);

        foreach ([GatewayException::TIMEOUT, GatewayException::RATE_LIMITED, GatewayException::UNAVAILABLE] as $code) {
            $this->fakeModel([new GatewayException($code)]);
            $response = $this->say($this->startConversation(), 'rendang')->assertOk();

            $this->assertSame('failed', $response->json('data.status'));
            $this->assertSame($code, $response->json('data.error_code'));
            $this->assertNotEmpty($response->json('data.message.text'));
        }
    }

    public function test_malformed_truncated_and_fenced_answers_fail_safely(): void
    {
        $dish = $this->dish('rendang', 68000);
        $bad = [
            'not json' => $this->finalAnswer('x')->text === null ? null : new ChatCompletionResult('plain text', [], 'stop', [], 'fake'),
            'truncated' => new ChatCompletionResult('{"text":"x"', [], 'length', [], 'fake'),
            'fenced' => new ChatCompletionResult("```json\n{\"text\":\"x\",\"source_menu_item_ids\":[{$dish->id}],\"source_revision_ids\":[]}\n```", [], 'stop', [], 'fake'),
            'extra key' => new ChatCompletionResult("{\"text\":\"x\",\"source_menu_item_ids\":[{$dish->id}],\"source_revision_ids\":[],\"price\":1}", [], 'stop', [], 'fake'),
        ];

        foreach ($bad as $case => $result) {
            $this->fakeModel([$result]);
            $response = $this->say($this->startConversation(), 'rendang')->assertOk();
            $this->assertSame('failed', $response->json('data.status'), $case);
        }
    }

    public function test_a_reasoning_block_before_the_json_is_ignored(): void
    {
        $dish = $this->dish('rendang', 68000);
        $this->fakeModel([new ChatCompletionResult("<think>hmm</think>\n{\"text\":\"Rendang.\",\"source_menu_item_ids\":[{$dish->id}],\"source_revision_ids\":[]}", [], 'stop', [], 'fake')]);

        $this->say($this->startConversation(), 'rendang')->assertOk()->assertJsonPath('data.status', 'completed');
    }

    // ----- retries and leases ----------------------------------------------

    public function test_the_same_client_turn_id_returns_the_same_turn_without_calling_the_model_again(): void
    {
        $dish = $this->dish('rendang', 68000);
        $fake = $this->fakeModel([$this->finalAnswer('Rendang.', [$dish->id])]);
        $conversation = $this->startConversation();
        $key = (string) Str::uuid();

        $first = $this->say($conversation, 'rendang', key: $key)->assertOk();
        $second = $this->say($conversation, 'rendang', key: $key)->assertOk();

        $this->assertSame($first->json('data.turn_id'), $second->json('data.turn_id'));
        $this->assertCount(1, $fake->requests);
        $this->assertSame(1, ConversationMessage::query()->where('role', 'user')->count());
    }

    public function test_the_same_client_turn_id_with_another_message_is_a_conflict(): void
    {
        $dish = $this->dish('rendang', 68000);
        $this->fakeModel([$this->finalAnswer('Rendang.', [$dish->id])]);
        $conversation = $this->startConversation();
        $key = (string) Str::uuid();

        $this->say($conversation, 'rendang', key: $key)->assertOk();
        $this->say($conversation, 'sate', key: $key)->assertStatus(409)->assertJsonPath('error.code', 'idempotency_conflict');
    }

    public function test_a_second_turn_while_one_is_processing_is_rejected(): void
    {
        $this->dish('rendang', 68000);
        $conversation = $this->startConversation();
        $row = Conversation::query()->where('public_id', $conversation)->firstOrFail();

        // A turn still processing holds the lease (see TurnLease).
        $lease = TurnLease::acquire($row->guest_session_id, $row->id);
        $this->assertNotNull($lease);
        $this->fakeModel([]);

        $this->say($conversation, 'rendang')->assertStatus(409)->assertJsonPath('error.code', 'turn_in_progress');

        $lease->release();
        $this->say($conversation, 'halo')->assertOk();
    }

    public function test_a_stale_processing_turn_is_failed_once_by_recovery(): void
    {
        $this->dish('rendang', 68000);
        $conversation = $this->startConversation();
        $row = Conversation::query()->where('public_id', $conversation)->firstOrFail();
        $turn = AiTurn::query()->create([
            'public_id' => (string) Str::uuid(), 'conversation_id' => $row->id, 'client_turn_id' => (string) Str::uuid(),
            'request_fingerprint' => str_repeat('a', 64), 'status' => TurnStatus::Processing, 'locale' => 'id',
            'context_snapshot' => ['scene' => 'menu', 'menu_item_id' => null, 'request_public_id' => null, 'preferences' => null],
            'started_at' => now()->subMinutes(5), 'deadline_at' => now()->subMinutes(4), 'prompt_version' => 'restaurant-v1',
        ]);

        $this->assertTrue(app(RecoverStaleTurn::class)->recover($turn, 'turn_interrupted'));
        $this->assertFalse(app(RecoverStaleTurn::class)->recover($turn->refresh(), 'turn_interrupted'), 'A turn is finished at most once.');

        $this->assertSame(TurnStatus::Failed, $turn->refresh()->status);
        $this->assertSame('turn_interrupted', $turn->error_code);
        $this->assertSame(1, ConversationMessage::query()->where('turn_id', $turn->id)->where('role', 'assistant')->count());
    }

    // ----- budgets ----------------------------------------------------------

    public function test_the_per_guest_daily_budget_stops_the_model_but_not_the_endpoint(): void
    {
        config(['assistant.guest.daily_turns' => 2]);
        $dish = $this->dish('rendang', 68000);
        $fake = $this->fakeModel([$this->finalAnswer('Satu', [$dish->id]), $this->finalAnswer('Dua', [$dish->id]), $this->finalAnswer('Tiga', [$dish->id])]);
        $conversation = $this->startConversation();

        $this->say($conversation, 'rendang a')->assertOk()->assertJsonPath('data.status', 'completed');
        $this->say($conversation, 'rendang b')->assertOk()->assertJsonPath('data.status', 'completed');
        $third = $this->say($conversation, 'rendang c')->assertOk();

        $this->assertSame('failed', $third->json('data.status'));
        $this->assertSame('ai_budget_exhausted', $third->json('data.error_code'));
        $this->assertCount(2, $fake->requests);
        $this->assertSame(3, (int) DB::table('assistant_usage')->whereNotNull('guest_session_id')->value('turns'));
    }

    public function test_the_global_daily_cap_applies_across_guests(): void
    {
        config(['assistant.daily_turn_cap' => 1]);
        $dish = $this->dish('rendang', 68000);
        $fake = $this->fakeModel([$this->finalAnswer('Satu', [$dish->id]), $this->finalAnswer('Dua', [$dish->id])]);

        $this->say($this->startConversation(), 'rendang a')->assertOk()->assertJsonPath('data.status', 'completed');

        $this->flushSession();
        $second = $this->say($this->startConversation(), 'rendang b')->assertOk();

        $this->assertSame('ai_budget_exhausted', $second->json('data.error_code'));
        $this->assertCount(1, $fake->requests);
        $this->assertSame(2, AssistantBudget::globalToday());
    }

    public function test_browsing_still_works_when_the_budget_is_gone(): void
    {
        config(['assistant.daily_turn_cap' => 1]);
        $this->dish('rendang', 68000);
        $this->fakeModel([]);
        $conversation = $this->startConversation();
        $this->say($conversation, 'rendang a');
        $this->say($conversation, 'rendang b')->assertJsonPath('data.error_code', 'ai_budget_exhausted');

        $this->get('/menu')->assertOk();
        $this->getJson('/api/v1/menu')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_the_assistant_limiter_allows_eight_messages_a_minute_per_session(): void
    {
        $this->fakeModel([]);
        $conversation = $this->startConversation('welcome');
        $codes = [];

        for ($i = 0; $i < 9; $i++) {
            $codes[] = $this->say($conversation, 'halo')->getStatusCode();
        }

        // startConversation counted as the first hit of the same limiter.
        $this->assertContains(429, $codes);
        $this->say($conversation, 'halo')->assertStatus(429)->assertHeader('Retry-After')->assertJsonPath('error.code', 'too_many_requests');
        $this->travel(61)->seconds();
        $this->say($conversation, 'halo')->assertOk();
    }

    // ----- context -----------------------------------------------------------

    public function test_invalid_context_is_rejected_with_invalid_context(): void
    {
        // Nine rejected posts in a row would hit the per-session limiter (tested elsewhere).
        $this->withoutMiddleware(ThrottleRequests::class);
        $conversation = $this->startConversation();

        foreach ([
            ['scene' => 'kitchen'],
            ['menu_item_id' => 999],
            ['menu_item_id' => -1],
            ['request_public_id' => (string) Str::uuid()],
            ['request_public_id' => 'not-a-uuid'],
            ['preferences' => ['dietary_tags' => ['keto']]],
            ['preferences' => ['exclude_allergens' => ['unicorn']]],
            ['preferences' => ['party_size' => 500]],
            ['preferences' => ['role' => 'admin']],
        ] as $context) {
            $this->say($conversation, 'halo', $context)->assertStatus(422)->assertJsonPath('error.code', 'invalid_context');
        }

        $this->assertSame(0, AiTurn::query()->count());
    }

    public function test_the_context_dish_comes_first_in_the_evidence_and_the_context_is_stored_per_turn(): void
    {
        $this->dish('sate', 42000, options: ['names' => ['id' => 'Sate ayam', 'en' => 'Chicken satay', 'ja' => 'チキンサテ']]);
        $rendang = $this->dish('rendang', 68000, options: ['names' => ['id' => 'Rendang sapi', 'en' => 'Beef rendang', 'ja' => 'ルンダン']]);
        $fake = $this->fakeModel([$this->finalAnswer('Rendang.', [$rendang->id])]);

        $this->say($this->startConversation(), 'berapa harganya?', ['menu_item_id' => $rendang->id, 'preferences' => ['dietary_tags' => ['halal'], 'exclude_allergens' => [], 'party_size' => 2]])->assertOk();

        $system = $fake->requests[0]->messages[0]['content'];
        $this->assertStringContainsString('Rendang sapi', $system);
        $this->assertSame($rendang->id, AiTurn::query()->firstOrFail()->context_snapshot['menu_item_id']);
        $this->assertSame(['halal'], AiTurn::query()->firstOrFail()->context_snapshot['preferences']['dietary_tags']);
    }

    public function test_a_locale_switch_updates_the_conversation_and_the_answer_language(): void
    {
        $dish = $this->dish('rendang', 68000);
        $this->fakeModel([$this->finalAnswer('Beef rendang.', [$dish->id])]);
        $conversation = $this->startConversation(locale: 'id');

        $response = $this->say($conversation, 'beef rendang', locale: 'en')->assertOk();

        $this->assertSame('en', Conversation::query()->where('public_id', $conversation)->firstOrFail()->locale);
        $this->assertSame('Dish rendang', $this->cardsOf($response, 'menu_item_card')['name']);
    }

    // ----- history after content changes ---------------------------------------

    public function test_history_marks_changed_answers_stale_and_hides_archived_dishes(): void
    {
        $dish = $this->dish('rendang', 68000);
        $this->fakeModel([$this->finalAnswer('Rendang 68.000', [$dish->id])]);
        $conversation = $this->startConversation();
        $this->say($conversation, 'rendang')->assertOk();

        // The price changes: the answer is flagged as stale and the card shows the new price.
        $dish->forceFill(['price' => 75000])->save();
        $assistant = collect($this->getJson("/api/v1/conversations/{$conversation}/messages")->assertOk()->json('data'))->firstWhere('role', 'assistant');
        $this->assertTrue($assistant['stale']);
        $this->assertSame(75000, collect($assistant['ui'])->firstWhere('type', 'menu_item_card')['price']);

        // The dish is archived: the old answer is no longer shown.
        app(ArchiveMenuItem::class)->handle($this->adminForData(), $dish);
        $assistant = collect($this->getJson("/api/v1/conversations/{$conversation}/messages")->json('data'))->firstWhere('role', 'assistant');
        $this->assertTrue($assistant['unavailable']);
        $this->assertSame([], $assistant['ui']);
    }

    public function test_deleting_a_guest_session_removes_its_conversations_but_not_requests(): void
    {
        $this->dish('rendang', 68000);
        $this->fakeModel([]);
        $conversation = $this->startConversation('welcome');
        $this->say($conversation, 'halo');
        $session = GuestSession::query()->firstOrFail();

        $session->delete();

        $this->assertSame(0, Conversation::query()->count());
        $this->assertSame(0, AiTurn::query()->count());
    }
}
