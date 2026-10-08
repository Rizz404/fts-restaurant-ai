<?php

namespace Tests\Feature;

use App\Models\GuestRequest;
use App\Models\MenuItem;
use App\Models\RequestDraft;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\Support\BuildsRestaurantData;
use Tests\Support\TalksToAssistant;
use Tests\TestCase;

/**
 * Plan 7: the whole guest journey with a fake model. Question → source card
 * → reservation draft (once) → contact in the review step → button
 * confirmation → pending request → staff confirms → the guest sees it.
 */
class RestaurantJourneyTest extends TestCase
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

    public function test_from_a_menu_question_to_a_confirmed_reservation_in_each_language(): void
    {
        foreach (['id', 'en', 'ja'] as $locale) {
            $this->flushSession();
            GuestRequest::query()->delete(); // requests first: a request restricts the deletion of its draft
            RequestDraft::query()->delete();
            DB::table('reservation_slot_usage')->delete();

            $staff = $this->staff('front_of_house');
            $dish = MenuItem::query()->where('slug', 'rendang')->first() ?? $this->dish('rendang', 68000, [], ['halal'], ['chef' => true]);
            [$date, $slot] = $this->openDay();

            // 1. The guest asks about a dish and gets a card built from the database.
            $this->fakeModel([$this->toolCall('search_menu', ['query' => 'rendang']), $this->finalAnswer('Ada rendang.', [$dish->id])]);
            $conversation = $this->startConversation('menu', $locale);
            $answer = $this->say($conversation, 'ada rendang?', locale: $locale)->assertOk();
            $this->assertSame(68000, $this->cardsOf($answer, 'menu_item_card')['price']);

            // 2. A reservation request becomes one draft, with no contact asked in the chat.
            $this->fakeModel([$this->toolCall('prepare_reservation', ['date' => $date, 'slot' => $slot, 'party_size' => 4])]);
            $turn = $this->say($conversation, 'tolong buatkan reservasi untuk 4 orang', ['scene' => 'reservation'], locale: $locale)->assertOk();
            $card = $this->cardsOf($turn, 'request_draft');
            $this->assertSame(1, RequestDraft::query()->count());
            $this->assertSame(0, GuestRequest::query()->count());

            // 3. The guest opens the review, enters the contact, and confirms with the button.
            $draftId = $card['draft_id'];
            $this->get($turn->json('data.actions.0.path'))->assertOk()->assertSee($draftId);
            $this->patchJson("/api/v1/request-drafts/{$draftId}", [
                'payload' => $this->reservationPayload($date, $slot, 4), 'expected_revision' => 1, 'contact_name' => 'Tamu Uji', 'contact_phone' => '+62 812 3456 7890',
            ])->assertOk();
            $confirmed = $this->postJson("/api/v1/request-drafts/{$draftId}/confirm", ['expected_revision' => 2, 'idempotency_key' => (string) Str::uuid()])->assertCreated();
            $reference = $confirmed->json('data.request.reference');
            $this->assertSame('pending', $confirmed->json('data.request.status'));

            // 4. Staff of the front-of-house team confirm; the guest sees the new status on the booking page.
            $request = GuestRequest::query()->where('reference', $reference)->firstOrFail();
            $this->actingAs($staff)->patchJson("/api/v1/staff/requests/{$request->public_id}/status", ['status' => 'confirmed', 'expected_version' => 1, 'idempotency_key' => (string) Str::uuid()])->assertOk();
            $this->actingAs($staff)->postJson("/api/v1/staff/requests/{$request->public_id}/messages", ['text' => 'Meja siap.', 'visibility' => 'public', 'idempotency_key' => (string) Str::uuid()])->assertCreated();
            auth()->logout();

            $this->getJson("/api/v1/requests/{$request->public_id}/messages")->assertOk()
                ->assertJsonPath('meta.request.status', 'confirmed')->assertJsonPath('data.0.text', 'Meja siap.');
            $this->get("/bookings/{$reference}?lang={$locale}")->assertOk()->assertSee($reference);

            // 5. The chat history shows the same draft as the confirmed request, not as a pending draft.
            $history = collect($this->getJson("/api/v1/conversations/{$conversation}/messages")->json('data'))->flatMap(fn ($m) => $m['ui'])->pluck('type');
            $this->assertContains('request_confirmation', $history->all());
            $this->assertNotContains('request_draft', $history->all());
        }
    }

    public function test_an_order_journey_prices_from_the_database_and_reaches_the_kitchen_only(): void
    {
        $dish = $this->dish('rendang', 68000);
        $kitchen = $this->staff('kitchen');
        $foh = $this->staff('front_of_house');

        $this->fakeModel([$this->toolCall('prepare_order', ['fulfilment' => 'pickup', 'requested_time' => '12:00', 'items' => [['menu_item_id' => $dish->id, 'quantity' => 2]]])]);
        $turn = $this->say($this->startConversation('order'), 'pesan 2 rendang untuk diambil jam 12', ['scene' => 'order'])->assertOk();
        $draft = $this->cardsOf($turn, 'request_draft');
        $this->assertStringContainsString('136.000', $draft['total_formatted']);

        $this->patchJson("/api/v1/request-drafts/{$draft['draft_id']}", [
            'payload' => ['fulfilment' => 'pickup', 'requested_time' => '12:00', 'items' => [['menu_item_id' => $dish->id, 'quantity' => 2]], 'notes' => null],
            'expected_revision' => 1, 'contact_name' => 'Tamu', 'contact_phone' => '+62 812 3456 7890',
        ])->assertOk();
        $this->postJson("/api/v1/request-drafts/{$draft['draft_id']}/confirm", ['expected_revision' => 2, 'idempotency_key' => (string) Str::uuid()])->assertCreated();

        $order = GuestRequest::query()->firstOrFail();
        $this->assertSame(136000, $order->pricing['total']);
        $this->assertSame(1, $kitchen->notifications()->count());
        $this->assertSame(0, $foh->notifications()->count());
        $this->actingAs($kitchen)->get("/admin/requests/{$order->public_id}")->assertOk();
        $this->actingAs($foh)->get("/admin/requests/{$order->public_id}")->assertNotFound();
    }

    public function test_status_questions_use_the_tools_and_never_leak_other_guests(): void
    {
        [, $other] = $this->makeGuest();
        $theirs = $this->feedbackFor($other, 'Rahasia orang lain');

        $this->fakeModel([$this->toolCall('get_request_status', ['reference' => $theirs->reference])]);
        $response = $this->say($this->startConversation('bookings'), "status {$theirs->reference}", ['scene' => 'bookings'])->assertOk();

        $this->assertSame(__('deck.assistant.tools.request_not_found', [], 'id'), $response->json('data.message.text'));
        $this->assertSame([], $response->json('data.ui'));
        $this->assertStringNotContainsString('Rahasia', $response->getContent());
        $this->assertStringNotContainsString($theirs->public_id, $response->getContent());
    }

    public function test_the_guest_can_ask_about_their_own_request_with_the_page_context(): void
    {
        $this->flushSession();
        [$date, $slot] = $this->openDay();
        $draftId = $this->postJson('/api/v1/request-drafts', ['type' => 'reservation', 'payload' => $this->reservationPayload($date, $slot, 2), 'contact_name' => 'T', 'contact_phone' => '+62 812 3456 7890'])->json('data.draft_id');
        $confirmed = $this->postJson("/api/v1/request-drafts/{$draftId}/confirm", ['expected_revision' => 1, 'idempotency_key' => (string) Str::uuid()])->json('data.request');
        $request = GuestRequest::query()->where('reference', $confirmed['reference'])->firstOrFail();

        $fake = $this->fakeModel([$this->toolCall('get_request_status', ['reference' => $request->reference])]);
        $response = $this->say($this->startConversation('bookings'), 'bagaimana status booking ini?', ['scene' => 'bookings', 'request_public_id' => $request->public_id])->assertOk();

        $this->assertStringContainsString($request->reference, $fake->requests[0]->messages[0]['content'], 'The prompt knows the reference on screen, nothing more.');
        $this->assertStringNotContainsString('812', $fake->requests[0]->messages[0]['content']);
        $this->assertSame('request_detail', $this->cardsOf($response, 'request_detail')['type']);
    }

    public function test_navigation_is_offered_as_a_button_and_nothing_moves_by_itself(): void
    {
        $this->dish('rendang', 68000);
        $this->fakeModel([$this->toolCall('present_slide', ['slide' => 'menu']), $this->finalAnswer('Silakan buka menu.', [1])]);

        $response = $this->say($this->startConversation('welcome'), 'bawa saya ke bagian makanan', ['scene' => 'welcome'])->assertOk();

        $this->assertContains('open_scene', array_column($response->json('data.actions'), 'type'));
        $this->assertContains('/menu', array_column($response->json('data.actions'), 'path'));
        $this->assertSame(0, GuestRequest::query()->count());
    }

    public function test_card_text_from_staff_content_is_never_html(): void
    {
        $this->dish('rendang', 68000, options: ['names' => ['id' => '<img src=x onerror=alert(1)>', 'en' => 'x', 'ja' => 'x']]);
        $this->fakeModel([$this->toolCall('search_menu', ['query' => 'img']), $this->finalAnswer('ok', [1])]);

        $response = $this->say($this->startConversation(), 'img')->assertOk();

        // The API returns the raw text; the renderer places it with textContent (see safe-card-renderer tests).
        $this->assertSame('<img src=x onerror=alert(1)>', $this->cardsOf($response, 'menu_item_card')['name']);
        $this->get('/menu')->assertOk()->assertDontSee('<img src=x onerror=alert(1)>', false);
    }
}
