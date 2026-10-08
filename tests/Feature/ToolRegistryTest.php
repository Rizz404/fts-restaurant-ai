<?php

namespace Tests\Feature;

use App\Models\AiTurn;
use App\Models\Conversation;
use App\Models\GuestRequest;
use App\Models\RequestDraft;
use App\Models\ToolInvocation;
use App\Modules\Assistant\Tools\ToolArgumentException;
use App\Modules\Assistant\Tools\ToolArguments;
use App\Modules\Assistant\Tools\ToolContext;
use App\Modules\Assistant\Tools\ToolExecutor;
use App\Modules\Assistant\Tools\ToolRegistry;
use App\Modules\Assistant\Tools\ToolResult;
use App\Modules\Conversation\Actions\StartConversation;
use App\Modules\Conversation\Enums\TurnStatus;
use App\Modules\Experience\Enums\SceneCode;
use App\Modules\Identity\Guest\GuestContext;
use App\Modules\Menu\Actions\ArchiveMenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\BuildsRestaurantData;
use Tests\TestCase;

/**
 * Plan 7: the ten tools, their schemas, strict argument validation, scope,
 * and the absence of any side-effecting or identity-bearing argument.
 */
class ToolRegistryTest extends TestCase
{
    use BuildsRestaurantData;
    use RefreshDatabase;

    private const NAMES = [
        'search_menu', 'get_menu_item', 'search_info', 'check_availability', 'list_my_requests',
        'get_request_status', 'prepare_reservation', 'prepare_order', 'prepare_feedback', 'present_slide',
    ];

    private GuestContext $guest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();
        $this->freezeClock();
        config(['assistant.tools.enabled' => true]);
        // Tools run inside an HTTP request in production; give this one a session for the grants.
        request()->setLaravelSession(app('session.store'));
        [, $this->guest] = $this->makeGuest();
    }

    private function context(?GuestContext $guest = null, ?array $preferences = null): ToolContext
    {
        $guest ??= $this->guest;
        $conversation = app(StartConversation::class)->handle($guest, 'id', SceneCode::Menu);
        $turn = AiTurn::query()->create([
            'public_id' => (string) Str::uuid(), 'conversation_id' => $conversation->id, 'client_turn_id' => (string) Str::uuid(),
            'request_fingerprint' => str_repeat('b', 64), 'status' => TurnStatus::Processing, 'locale' => 'id',
            'context_snapshot' => ['scene' => 'menu', 'menu_item_id' => null, 'request_public_id' => null, 'preferences' => $preferences],
            'started_at' => now(), 'deadline_at' => now()->addMinute(), 'prompt_version' => 'restaurant-v2',
        ]);

        return new ToolContext($turn, $conversation, 'id', $preferences);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function callTool(string $name, array|string $arguments, ?ToolContext $context = null, ?GuestContext $guest = null): ToolResult
    {
        return app(ToolExecutor::class)->run($guest ?? $this->guest, [
            'id' => 'call_'.Str::random(6), 'name' => $name, 'arguments' => is_array($arguments) ? (string) json_encode($arguments === [] ? new \stdClass : $arguments) : $arguments,
        ], $context ?? $this->context($guest));
    }

    public function test_exactly_the_ten_tools_are_registered_with_object_schemas(): void
    {
        $registry = app(ToolRegistry::class);

        $this->assertEqualsCanonicalizing(self::NAMES, array_keys($registry->all()));
        foreach ($registry->definitions() as $definition) {
            $function = $definition['function'];
            $this->assertSame('function', $definition['type']);
            $this->assertSame('object', $function['parameters']['type'], $function['name']);
            $this->assertFalse($function['parameters']['additionalProperties'], $function['name']);
            $this->assertNotEmpty($function['description']);
        }
    }

    public function test_no_tool_can_change_state_beyond_a_draft_or_carry_identity(): void
    {
        $forbidden = ['guest_session_id', 'session', 'role', 'assignee', 'assignee_id', 'url', 'href', 'price', 'api_key', 'name', 'phone', 'email', 'contact', 'contact_name', 'contact_phone', 'contact_email'];

        foreach (app(ToolRegistry::class)->definitions() as $definition) {
            $properties = array_keys($definition['function']['parameters']['properties'] ?? []);
            $this->assertSame([], array_intersect($forbidden, $properties), $definition['function']['name'].' accepts a forbidden argument');
        }
        // Only list_my_requests takes a status, as a read-only filter; no tool can set one.
        foreach (app(ToolRegistry::class)->definitions() as $definition) {
            if ($definition['function']['name'] !== 'list_my_requests') {
                $this->assertArrayNotHasKey('status', $definition['function']['parameters']['properties'] ?? []);
            }
        }
        foreach (['create_request', 'confirm_request', 'cancel_request', 'set_status', 'set_availability', 'internal_note', 'http_request', 'run_sql'] as $name) {
            $this->assertNull(app(ToolRegistry::class)->get($name), $name);
        }
    }

    public function test_the_registry_is_empty_when_tools_are_disabled(): void
    {
        config(['assistant.tools.enabled' => false]);

        $this->assertTrue((new ToolRegistry(app()))->isEmpty());
    }

    public function test_an_unknown_tool_is_refused_and_logged(): void
    {
        $result = $this->callTool('drop_database', []);

        $this->assertSame(ToolResult::UNKNOWN_TOOL, $result->code);
        $this->assertSame(['unknown_tool'], [$result->data['error']]);
        $this->assertSame('unknown_tool', ToolInvocation::query()->firstOrFail()->status);
    }

    public function test_unknown_keys_broken_json_and_non_objects_are_validation_failures(): void
    {
        $dish = $this->dish('rendang', 68000);

        foreach ([
            'unknown key' => ['item_id' => $dish->id, 'guest_session_id' => 7],
            'string id' => ['item_id' => (string) $dish->id],
            'float id' => ['item_id' => 1.5],
            'zero id' => ['item_id' => 0],
        ] as $case => $arguments) {
            $result = $this->callTool('get_menu_item', $arguments);
            $this->assertSame(ToolResult::VALIDATION_FAILED, $result->code, $case);
        }

        $this->assertSame(ToolResult::VALIDATION_FAILED, $this->callTool('get_menu_item', '{"item_id": 1')->code, 'broken JSON');
        $this->assertSame(ToolResult::VALIDATION_FAILED, $this->callTool('get_menu_item', '[1,2]')->code, 'a list is not an object');
        $this->assertSame(ToolResult::VALIDATION_FAILED, $this->callTool('get_menu_item', '"text"')->code, 'a string is not an object');
        $this->assertSame(ToolResult::OK, $this->callTool('get_menu_item', ['item_id' => $dish->id])->code);
    }

    public function test_enums_ranges_and_domain_values_are_enforced_for_each_tool(): void
    {
        $this->category('mains');
        $cases = [
            ['search_menu', ['limit' => 99]],
            ['search_menu', ['category' => 'dinner-of-doom']],
            ['search_menu', ['dietary_tags' => ['keto']]],
            ['search_menu', ['exclude_allergens' => ['unicorn']]],
            ['search_menu', ['max_price' => 0]],
            ['search_menu', ['query' => str_repeat('x', 201)]],
            ['search_info', ['query' => '']],
            ['search_info', ['query' => 'jam', 'category' => 'gossip']],
            ['check_availability', ['date' => '07-10-2026', 'party_size' => 2]],
            ['check_availability', ['date' => '2026-10-08', 'party_size' => 0]],
            ['check_availability', ['date' => '2026-10-08', 'party_size' => 11]],
            ['list_my_requests', ['page' => 0]],
            ['list_my_requests', ['status' => 'archived']],
            ['list_my_requests', ['type' => 'refund']],
            ['present_slide', ['slide' => 'kitchen']],
            ['present_slide', ['slide' => 'menu', 'focus' => ['url' => 'https://evil.example']]],
            ['prepare_reservation', ['date' => '2026-10-08', 'slot' => '10:00', 'party_size' => 2, 'name' => 'Tamu']],
            ['prepare_reservation', ['date' => '2026-10-08', 'slot' => '03:00', 'party_size' => 2]],
            ['prepare_reservation', ['date' => '2026-10-08', 'slot' => '10:00', 'party_size' => 99]],
            ['prepare_order', ['fulfilment' => 'delivery', 'requested_time' => '12:00', 'items' => [['menu_item_id' => 1, 'quantity' => 1]]]],
            ['prepare_order', ['fulfilment' => 'pickup', 'requested_time' => '12:00', 'items' => []]],
            ['prepare_order', ['fulfilment' => 'pickup', 'requested_time' => '12:00', 'items' => [['menu_item_id' => 1, 'quantity' => 1, 'price' => 1]]]],
            ['prepare_feedback', ['type' => 'feedback', 'subject' => 'x', 'body' => 'y']],
            ['prepare_feedback', ['type' => 'special_request', 'kind' => 'complaint', 'subject' => 'x', 'body' => 'y']],
            ['prepare_feedback', ['type' => 'feedback', 'kind' => 'compliment', 'subject' => '', 'body' => 'y']],
            ['prepare_feedback', ['type' => 'feedback', 'kind' => 'compliment', 'subject' => 'x', 'body' => 'y', 'phone' => '+62 812']],
        ];

        foreach ($cases as [$name, $arguments]) {
            $this->assertSame(ToolResult::VALIDATION_FAILED, $this->callTool($name, $arguments)->code, $name.' '.json_encode($arguments));
        }
        $this->assertSame(0, GuestRequest::query()->count());
        $this->assertDatabaseCount('request_drafts', 0);
    }

    public function test_get_menu_item_returns_only_published_dishes_and_hides_unverified_claims(): void
    {
        $verified = $this->dish('rendang', 68000, ['soy' => 'contains'], ['halal']);
        $unverified = $this->unverifiedDish('soto', 38000, ['celery' => 'contains'], ['halal']);
        $archived = $this->dish('sate', 42000);
        app(ArchiveMenuItem::class)->handle($this->adminForData(), $archived);

        $this->assertSame(ToolResult::NOT_FOUND, $this->callTool('get_menu_item', ['item_id' => $archived->id])->code);
        $this->assertSame(ToolResult::NOT_FOUND, $this->callTool('get_menu_item', ['item_id' => 9999])->code);

        $ok = $this->callTool('get_menu_item', ['item_id' => $verified->id]);
        $this->assertSame(['soy'], $ok->data['dish']['allergens']['contains']);
        $this->assertSame([$verified->id], $ok->evidence->menuIds());

        $bad = $this->callTool('get_menu_item', ['item_id' => $unverified->id]);
        $this->assertFalse($bad->data['dish']['allergen_verified']);
        $this->assertSame(['contains' => [], 'may_contain' => []], $bad->data['dish']['allergens']);
        $this->assertSame([], $bad->data['dish']['dietary_tags']);
    }

    public function test_requests_are_scoped_to_the_guest_session(): void
    {
        $mine = $this->feedbackFor($this->guest, 'Mine');
        [, $stranger] = $this->makeGuest();
        $theirs = $this->feedbackFor($stranger, 'Theirs');

        $list = $this->callTool('list_my_requests', []);
        $this->assertTrue($list->terminal);
        $this->assertSame([$mine->reference], array_column($list->data['requests'], 'reference'));

        $own = $this->callTool('get_request_status', ['reference' => strtolower($mine->reference)]);
        $this->assertSame(ToolResult::OK, $own->code);
        $this->assertSame('pending', $own->data['status']);

        $foreign = $this->callTool('get_request_status', ['reference' => $theirs->reference]);
        $this->assertSame(ToolResult::NOT_FOUND, $foreign->code);
        $this->assertSame(['error' => 'request_not_found'], $foreign->data);
        $this->assertStringNotContainsString('Theirs', json_encode([$foreign->ui, $foreign->text, $foreign->data]));

        // A reference that does not exist looks exactly the same as one that is not yours.
        $missing = $this->callTool('get_request_status', ['reference' => 'RST-20261007-ZZZZZZZZ']);
        $this->assertSame($foreign->data, $missing->data);
        $this->assertSame($foreign->text, $missing->text);
    }

    public function test_check_availability_is_read_only_and_terminal(): void
    {
        [$date] = $this->openDay();

        $result = $this->callTool('check_availability', ['date' => $date, 'party_size' => 4]);

        $this->assertTrue($result->terminal);
        $this->assertSame('availability_slots', $result->ui[0]['type']);
        $this->assertNotEmpty($result->data['open_slots']);
        $this->assertDatabaseCount('reservation_slot_usage', 0);

        $closed = $this->callTool('check_availability', ['date' => '2026-12-31', 'party_size' => 4]);
        $this->assertSame(ToolResult::VALIDATION_FAILED, $closed->code, 'Outside the booking window the tool reports the error as data.');
    }

    public function test_prepare_tools_save_one_draft_with_no_contact_and_never_a_request(): void
    {
        [$date, $slot] = $this->openDay();
        $context = $this->context();

        $result = $this->callTool('prepare_reservation', ['date' => $date, 'slot' => $slot, 'party_size' => 4], $context);

        $this->assertSame(ToolResult::OK, $result->code);
        $this->assertTrue($result->terminal);
        $this->assertSame('request_draft', $result->ui[0]['type']);
        $this->assertSame('review_request_draft', $result->actions[0]['type']);
        $draft = RequestDraft::query()->firstOrFail();
        $this->assertNull($draft->contact_name);
        $this->assertNull($draft->contact_phone);
        $this->assertSame($context->turn->id, $draft->originating_turn_id);
        $this->assertSame(0, GuestRequest::query()->count());
        $this->assertDatabaseCount('reservation_slot_usage', 0);
    }

    public function test_the_invocation_log_never_stores_free_text_or_results(): void
    {
        $context = $this->context();
        $this->callTool('prepare_feedback', ['type' => 'feedback', 'kind' => 'compliment', 'subject' => 'RAHASIA-SUBJEK', 'body' => 'RAHASIA-ISI'], $context);
        $this->callTool('search_info', ['query' => 'RAHASIA-QUERY'], $context);

        $logged = json_encode(ToolInvocation::query()->get()->toArray());
        $this->assertStringNotContainsString('RAHASIA', $logged);
        $this->assertSame(['ok', 'ok'], ToolInvocation::query()->orderBy('id')->pluck('status')->all());
        $this->assertNotEmpty(ToolInvocation::query()->first()->validated_input['fingerprint']);
    }

    public function test_tool_arguments_helper_rejects_unknown_keys_and_decodes_strict_json(): void
    {
        $this->expectException(ToolArgumentException::class);

        try {
            $this->assertSame([], ToolArguments::decode(''));
            $this->assertSame(['a' => 1], ToolArguments::decode('{"a":1}'));
            ToolArguments::validate(['a' => 1, 'b' => 2], ['a' => ['integer']]);
        } catch (ToolArgumentException $e) {
            $this->assertSame(['b' => ['unknown argument']], $e->fields);
            throw $e;
        }
    }

    public function test_present_slide_only_adds_buttons_from_fixed_routes(): void
    {
        $dish = $this->dish('rendang', 68000);
        $this->category('mains');

        $slide = $this->callTool('present_slide', ['slide' => 'reservation']);
        $this->assertSame('/reservation', $slide->actions[0]['path']);
        $this->assertFalse($slide->terminal);

        $focus = $this->callTool('present_slide', ['slide' => 'menu', 'focus' => ['menu_item_id' => $dish->id]]);
        $this->assertSame(['/menu', '/menu/rendang'], array_column($focus->actions, 'path'));

        $category = $this->callTool('present_slide', ['slide' => 'menu', 'focus' => ['category' => 'mains']]);
        $this->assertSame('/menu?category=mains', $category->actions[0]['path']);

        $this->assertSame(ToolResult::NOT_FOUND, $this->callTool('present_slide', ['slide' => 'menu', 'focus' => ['menu_item_id' => 9999]])->code);
        $this->assertDatabaseCount('request_drafts', 0);
        $this->assertSame(0, Conversation::query()->where('guest_session_id', 0)->count());
    }
}
