<?php

namespace Tests\Feature;

use App\Models\ClosedDate;
use App\Models\GuestRequest;
use App\Models\RequestDraft;
use App\Models\RequestEvent;
use App\Models\ReservationSlotUsage;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\BuildsRestaurantData;
use Tests\TestCase;

/**
 * Plan 6: request drafts through the guest API (create, edit with revisions,
 * expiry, validation per type) and explicit confirmation with idempotency.
 */
class RequestDraftTest extends TestCase
{
    use BuildsRestaurantData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();
        $this->freezeClock();
        $this->withoutMiddleware(ThrottleRequests::class);
        RateLimiter::clear('guest-session-create');
    }

    /**
     * @param  array<string, mixed>  $contact
     */
    private function createDraft(string $type, array $payload, array $contact = []): TestResponse
    {
        return $this->postJson('/api/v1/request-drafts', ['type' => $type, 'payload' => $payload] + $contact);
    }

    /**
     * @return array<string, string>
     */
    private function contact(): array
    {
        return ['contact_name' => 'Tamu Uji', 'contact_phone' => '+62 812 3456 7890'];
    }

    private function confirm(string $draftId, int $revision, ?string $key = null): TestResponse
    {
        return $this->postJson("/api/v1/request-drafts/{$draftId}/confirm", ['expected_revision' => $revision, 'idempotency_key' => $key ?? (string) Str::uuid()]);
    }

    public function test_a_reservation_draft_is_saved_for_review_and_holds_nothing(): void
    {
        $response = $this->createDraft('reservation', $this->reservationPayload())->assertCreated();

        $response->assertJsonPath('data.type', 'reservation')
            ->assertJsonPath('data.revision', 1)
            ->assertJsonPath('data.expired', false)
            ->assertJsonPath('data.team_label', __('ui.teams.front_of_house'));
        $this->assertSame(now()->addMinutes(15)->toIso8601String(), CarbonImmutable::parse($response->json('data.expires_at'))->toIso8601String());
        $this->assertSame(0, GuestRequest::query()->count());
        $this->assertDatabaseCount('reservation_slot_usage', 0);
    }

    public function test_each_type_goes_to_its_team(): void
    {
        $this->dish('rendang', 68000);
        $order = ['fulfilment' => 'pickup', 'requested_time' => '12:00', 'items' => [['menu_item_id' => 1, 'quantity' => 2]], 'notes' => null];

        $this->createDraft('order', $order)->assertCreated()->assertJsonPath('data.team_label', __('ui.teams.kitchen'));
        $this->createDraft('feedback', $this->feedbackPayload())->assertCreated()->assertJsonPath('data.team_label', __('ui.teams.front_of_house'));
        $this->createDraft('special_request', ['subject' => 'Kue ulang tahun', 'body' => 'Mohon siapkan lilin.', 'date' => null])->assertCreated()->assertJsonPath('data.team_label', __('ui.teams.front_of_house'));
    }

    public function test_reservation_payloads_that_break_the_rules_are_rejected_with_field_errors(): void
    {
        [$date, $slot] = $this->openDay();
        $cases = [
            'payload.slot' => ['slot' => '07:00'],
            'payload.date' => ['date' => '2026-02-30'],
            'payload.party_size' => ['party_size' => 11],
            'payload.seating_preference' => ['seating_preference' => 'balcony'],
            'payload.notes' => ['notes' => str_repeat('x', 501)],
        ];

        foreach ($cases as $field => $override) {
            $this->createDraft('reservation', array_replace($this->reservationPayload($date, $slot), $override))
                ->assertStatus(422)
                ->assertJsonValidationErrors([$field], 'error.fields');
        }

        $this->createDraft('reservation', $this->reservationPayload($date, $slot) + ['status' => 'confirmed'])
            ->assertStatus(422)->assertJsonValidationErrors(['payload.status'], 'error.fields');
        $this->assertSame(0, RequestDraft::query()->count());
    }

    public function test_reservation_time_rules_hours_lead_time_window_and_closed_dates(): void
    {
        [$date, $slot] = $this->openDay();
        $today = now(config('restaurant.timezone'))->format('Y-m-d');

        // Opening is 10:00 and the clock is 09:00 with a 60 minute lead time.
        $this->createDraft('reservation', $this->reservationPayload($today, '09:30'))->assertStatus(422)->assertJsonValidationErrors(['payload.slot'], 'error.fields');
        $this->createDraft('reservation', $this->reservationPayload($today, '11:00'))->assertCreated();
        $this->createDraft('reservation', $this->reservationPayload(now(config('restaurant.timezone'))->addDays(31)->format('Y-m-d'), '12:00'))
            ->assertStatus(422)->assertJsonValidationErrors(['payload.date'], 'error.fields');

        ClosedDate::query()->create(['date' => $date, 'reason' => 'Libur']);
        $this->createDraft('reservation', $this->reservationPayload($date, $slot))->assertStatus(422)->assertJsonValidationErrors(['payload.date'], 'error.fields');
    }

    public function test_order_and_feedback_payload_rules(): void
    {
        $dish = $this->dish('rendang', 68000);
        $this->dish('es-teler', 28000, options: ['available' => false]);
        $good = ['fulfilment' => 'pickup', 'requested_time' => '12:00', 'items' => [['menu_item_id' => $dish->id, 'quantity' => 1]], 'notes' => null];

        foreach ([
            'payload.items' => ['items' => []],
            'payload.items.0.quantity' => ['items' => [['menu_item_id' => $dish->id, 'quantity' => 21]]],
            'payload.items.0.menu_item_id' => ['items' => [['menu_item_id' => 0, 'quantity' => 1]]],
            'payload.fulfilment' => ['fulfilment' => 'delivery'],
            'payload.requested_time' => ['requested_time' => '03:00'],
        ] as $field => $override) {
            $this->createDraft('order', array_replace($good, $override))->assertStatus(422)->assertJsonValidationErrors([$field], 'error.fields');
        }

        // A sold-out dish is refused before the guest even reviews the draft.
        $this->createDraft('order', array_replace($good, ['items' => [['menu_item_id' => 2, 'quantity' => 1]]]))
            ->assertStatus(422)->assertJsonValidationErrors(['payload.items'], 'error.fields');

        $this->createDraft('feedback', ['kind' => 'rant'] + $this->feedbackPayload())->assertStatus(422);
        $this->createDraft('special_request', ['subject' => 'x', 'body' => str_repeat('b', 2001), 'date' => null])->assertStatus(422)->assertJsonValidationErrors(['payload.body'], 'error.fields');
        $this->createDraft('refund', [])->assertStatus(422);
    }

    public function test_contact_format_is_validated_but_not_required_to_save_a_draft(): void
    {
        $this->createDraft('reservation', $this->reservationPayload(), ['contact_phone' => 'call me'])->assertStatus(422);
        $this->createDraft('reservation', $this->reservationPayload(), ['contact_email' => 'not-an-email'])->assertStatus(422);
        $this->createDraft('reservation', $this->reservationPayload())->assertCreated();
    }

    public function test_editing_increments_the_revision_and_stale_edits_conflict(): void
    {
        $id = $this->createDraft('reservation', $this->reservationPayload(party: 2))->json('data.draft_id');
        $payload = $this->reservationPayload(party: 4);

        $this->patchJson("/api/v1/request-drafts/{$id}", ['payload' => $payload, 'expected_revision' => 1] + $this->contact())
            ->assertOk()->assertJsonPath('data.revision', 2)->assertJsonPath('data.payload.party_size', 4)->assertJsonPath('data.contact.name', 'Tamu Uji');

        $this->patchJson("/api/v1/request-drafts/{$id}", ['payload' => $payload, 'expected_revision' => 1])
            ->assertStatus(409)->assertJsonPath('error.code', 'revision_conflict');
        $this->patchJson("/api/v1/request-drafts/{$id}", ['payload' => $payload, 'type' => 'order', 'expected_revision' => 2])->assertStatus(422);
        $this->patchJson("/api/v1/request-drafts/{$id}", ['payload' => $payload])->assertStatus(422);
    }

    public function test_an_edit_renews_the_fifteen_minute_window_and_an_expired_draft_cannot_be_used(): void
    {
        $id = $this->createDraft('reservation', $this->reservationPayload(), $this->contact())->json('data.draft_id');

        $this->freezeClock('2026-10-07 09:10:00');
        $this->patchJson("/api/v1/request-drafts/{$id}", ['payload' => $this->reservationPayload(), 'expected_revision' => 1] + $this->contact())->assertOk();
        $this->freezeClock('2026-10-07 09:20:00');
        $this->getJson("/api/v1/request-drafts/{$id}")->assertOk()->assertJsonPath('data.expired', false);

        $this->freezeClock('2026-10-07 09:26:00');
        $this->getJson("/api/v1/request-drafts/{$id}")->assertOk()->assertJsonPath('data.expired', true);
        $this->confirm($id, 2)->assertStatus(409)->assertJsonPath('error.code', 'draft_expired');
        $this->patchJson("/api/v1/request-drafts/{$id}", ['payload' => $this->reservationPayload(), 'expected_revision' => 2])->assertStatus(409);
        $this->assertSame(0, GuestRequest::query()->count());
    }

    public function test_another_guest_cannot_see_edit_or_confirm_a_draft(): void
    {
        $id = $this->createDraft('reservation', $this->reservationPayload(), $this->contact())->json('data.draft_id');

        $this->flushSession();
        $this->getJson("/api/v1/request-drafts/{$id}")->assertNotFound();
        $this->createDraft('feedback', $this->feedbackPayload())->assertCreated(); // creates this guest's own session
        $this->patchJson("/api/v1/request-drafts/{$id}", ['payload' => $this->reservationPayload(), 'expected_revision' => 1])->assertNotFound();
        $this->confirm($id, 1)->assertNotFound();
        $this->get("/reservation?draft={$id}")->assertNotFound();
    }

    public function test_confirming_creates_a_pending_request_holds_seats_and_is_idempotent(): void
    {
        $id = $this->createDraft('reservation', $this->reservationPayload(party: 4), $this->contact())->json('data.draft_id');
        $key = (string) Str::uuid();

        $first = $this->confirm($id, 1, $key)->assertCreated();
        $reference = $first->json('data.request.reference');

        $this->assertMatchesRegularExpression('/^RST-20261007-[A-Z2-9]{8}$/', $reference);
        $first->assertJsonPath('data.type', 'request_confirmation')->assertJsonPath('data.request.status', 'pending');

        // A retry with the same key and a second click with a new key both return the same request.
        $this->confirm($id, 1, $key)->assertOk()->assertJsonPath('data.request.reference', $reference);
        $this->confirm($id, 1)->assertOk()->assertJsonPath('data.request.reference', $reference);

        $this->assertSame(1, GuestRequest::query()->count());
        [, $slot] = $this->openDay();
        $this->assertSame(4, (int) ReservationSlotUsage::query()->where('slot_start', $slot)->value('booked_seats'));
        $this->assertSame(1, RequestEvent::query()->where('event_code', 'created')->count());
    }

    public function test_the_same_key_for_a_different_draft_is_a_conflict(): void
    {
        $key = (string) Str::uuid();
        $a = $this->createDraft('feedback', $this->feedbackPayload('A'))->json('data');
        $b = $this->createDraft('feedback', $this->feedbackPayload('B'))->json('data');

        $this->confirm($a['draft_id'], 1, $key)->assertCreated();
        $this->confirm($b['draft_id'], 1, $key)->assertStatus(409)->assertJsonPath('error.code', 'idempotency_conflict');
    }

    public function test_a_confirmed_draft_can_no_longer_be_edited_and_a_stale_revision_does_not_confirm(): void
    {
        $id = $this->createDraft('feedback', $this->feedbackPayload())->json('data.draft_id');
        $this->patchJson("/api/v1/request-drafts/{$id}", ['payload' => $this->feedbackPayload('Edit'), 'expected_revision' => 1])->assertOk();

        $this->confirm($id, 1)->assertStatus(409)->assertJsonPath('error.code', 'revision_conflict');
        $this->confirm($id, 2)->assertCreated();

        $this->patchJson("/api/v1/request-drafts/{$id}", ['payload' => $this->feedbackPayload('Late'), 'expected_revision' => 2])
            ->assertStatus(409)->assertJsonPath('error.code', 'draft_consumed');
        $this->get('/bookings')->assertOk();
        $this->get("/order?draft={$id}")->assertNotFound();
    }

    public function test_contact_is_required_to_confirm_except_for_feedback(): void
    {
        $reservation = $this->createDraft('reservation', $this->reservationPayload())->json('data.draft_id');
        $special = $this->createDraft('special_request', ['subject' => 'Dekorasi', 'body' => 'Balon.', 'date' => null])->json('data.draft_id');
        $feedback = $this->createDraft('feedback', $this->feedbackPayload())->json('data.draft_id');

        $this->confirm($reservation, 1)->assertStatus(422)->assertJsonPath('error.code', 'contact_required')->assertJsonValidationErrors(['contact.name', 'contact.phone'], 'error.fields');
        $this->confirm($special, 1)->assertStatus(422)->assertJsonPath('error.code', 'contact_required');
        $this->confirm($feedback, 1)->assertCreated();

        $this->patchJson("/api/v1/request-drafts/{$reservation}", ['payload' => $this->reservationPayload(), 'expected_revision' => 1, 'contact_name' => 'Tamu', 'contact_phone' => '12345'])->assertOk();
        $this->confirm($reservation, 2)->assertStatus(422)->assertJsonPath('error.code', 'contact_required');
    }

    public function test_the_order_is_priced_from_the_database_and_frozen_at_confirmation(): void
    {
        $dish = $this->dish('rendang', 68000);
        $order = ['fulfilment' => 'pickup', 'requested_time' => '12:00', 'items' => [['menu_item_id' => $dish->id, 'quantity' => 2]], 'notes' => null];

        $draft = $this->createDraft('order', $order, $this->contact());
        $draft->assertCreated();
        $this->assertStringContainsString('136.000', $draft->json('data.total_formatted'));

        $dish->forceFill(['price' => 90000])->save();
        $this->assertStringContainsString('180.000', $this->getJson('/api/v1/request-drafts/'.$draft->json('data.draft_id'))->json('data.total_formatted'), 'The review shows live prices.');

        $request = $this->confirm($draft->json('data.draft_id'), 1)->assertCreated();
        $this->assertSame(180000, GuestRequest::query()->firstOrFail()->pricing['total']);

        // Later price changes do not touch a confirmed order.
        $dish->forceFill(['price' => 10000])->save();
        $this->assertSame(180000, GuestRequest::query()->firstOrFail()->refresh()->pricing['total']);
        $this->assertSame($request->json('data.request.reference'), GuestRequest::query()->firstOrFail()->reference);
    }

    public function test_a_dish_sold_out_before_confirmation_stops_the_order(): void
    {
        $dish = $this->dish('rendang', 68000);
        $id = $this->createDraft('order', ['fulfilment' => 'pickup', 'requested_time' => '12:00', 'items' => [['menu_item_id' => $dish->id, 'quantity' => 1]], 'notes' => null], $this->contact())->json('data.draft_id');

        $dish->forceFill(['is_available' => false])->save();

        $this->confirm($id, 1)->assertStatus(409)->assertJsonPath('error.code', 'item_unavailable');
        $this->assertSame(0, GuestRequest::query()->count());

        // The guest edits the draft, the dish is back, and confirmation works.
        $dish->forceFill(['is_available' => true])->save();
        $this->confirm($id, 1)->assertCreated();
    }

    public function test_draft_pages_open_only_for_the_owner_and_a_confirmed_draft_redirects_to_its_request(): void
    {
        $id = $this->createDraft('reservation', $this->reservationPayload(), $this->contact())->json('data.draft_id');

        $this->get("/reservation?draft={$id}")->assertOk()->assertSee($id);
        $this->get("/order?draft={$id}")->assertNotFound();
        $this->get('/reservation?draft=not-a-uuid')->assertNotFound();

        $reference = $this->confirm($id, 1)->json('data.request.reference');
        $this->get("/reservation?draft={$id}")->assertRedirect(route('experience.bookings.show', $reference));
    }
}
