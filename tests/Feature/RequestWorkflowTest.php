<?php

namespace Tests\Feature;

use App\Models\GuestRequest;
use App\Models\RequestEvent;
use App\Models\RequestMessage;
use App\Models\ReservationSlotUsage;
use App\Models\User;
use App\Modules\Identity\Guest\GuestContext;
use App\Modules\Identity\Guest\GuestSessions;
use App\Modules\Requests\Enums\RequestType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\BuildsRestaurantData;
use Tests\TestCase;

/**
 * Plan 6: the request lifecycle (staff status changes with versions and
 * reasons), the message thread (visibility, closed requests, idempotency),
 * guest cancellation, and assignment.
 */
class RequestWorkflowTest extends TestCase
{
    use BuildsRestaurantData;
    use RefreshDatabase;

    private GuestContext $guest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();
        $this->freezeClock();
        $this->withoutMiddleware(ThrottleRequests::class);
        RateLimiter::clear('guest-session-create');
        [, $this->guest] = $this->makeGuest();
    }

    private function setStatus(User $staff, GuestRequest $request, string $status, ?int $version = null, ?string $reason = null, ?string $key = null): TestResponse
    {
        return $this->actingAs($staff)->patchJson("/api/v1/staff/requests/{$request->public_id}/status", [
            'status' => $status,
            'expected_version' => $version ?? $request->refresh()->version,
            'reason' => $reason,
            'idempotency_key' => $key ?? (string) Str::uuid(),
        ]);
    }

    private function staffMessage(User $staff, GuestRequest $request, string $text, string $visibility = 'public', ?string $key = null): TestResponse
    {
        return $this->actingAs($staff)->postJson("/api/v1/staff/requests/{$request->public_id}/messages", [
            'text' => $text, 'visibility' => $visibility, 'idempotency_key' => $key ?? (string) Str::uuid(),
        ]);
    }

    /**
     * The guest acts through their own HTTP session.
     */
    private function asGuest(GuestContext $guest): void
    {
        auth()->logout();
        $this->flushSession();
        $this->withSession([GuestSessions::SESSION_KEY => $guest->guestSessionId]);
    }

    public function test_staff_moves_a_reservation_through_the_lifecycle_with_events_and_versions(): void
    {
        $staff = $this->staff('front_of_house');
        $request = $this->reservationFor($this->guest);

        $this->setStatus($staff, $request, 'confirmed')->assertOk()->assertJsonPath('data.status', 'confirmed')->assertJsonPath('data.version', 2);
        $this->setStatus($staff, $request, 'completed')->assertOk()->assertJsonPath('data.status', 'completed');

        $request->refresh();
        $this->assertNotNull($request->terminal_at);
        $this->assertSame(['created', 'status_changed', 'status_changed'], RequestEvent::query()->orderBy('id')->pluck('event_code')->all());
        $this->assertSame(['pending', 'confirmed'], RequestEvent::query()->where('event_code', 'status_changed')->orderBy('id')->pluck('previous_status')->all());
    }

    public function test_invalid_transitions_are_rejected(): void
    {
        $staff = $this->staff();
        $request = $this->reservationFor($this->guest);

        $this->setStatus($staff, $request, 'completed')->assertStatus(422)->assertJsonPath('error.code', 'invalid_transition');
        $this->setStatus($staff, $request, 'no_show')->assertStatus(422);
        $this->setStatus($staff, $request, 'confirmed')->assertOk();
        $this->setStatus($staff, $request, 'pending')->assertStatus(422);
        $this->setStatus($staff, $request, 'completed')->assertOk();
        $this->setStatus($staff, $request, 'confirmed')->assertStatus(422)->assertJsonPath('error.code', 'invalid_transition');
        $this->setStatus($staff, $request, 'cancelled', reason: 'x')->assertStatus(422);
        $this->actingAs($staff)->patchJson("/api/v1/staff/requests/{$request->public_id}/status", ['status' => 'archived', 'expected_version' => 1, 'idempotency_key' => (string) Str::uuid()])->assertStatus(422);
    }

    public function test_no_show_only_applies_to_confirmed_reservations(): void
    {
        $staff = $this->staff();
        $reservation = $this->reservationFor($this->guest);
        $this->setStatus($staff, $reservation, 'confirmed')->assertOk();
        $this->setStatus($staff, $reservation, 'no_show')->assertOk()->assertJsonPath('data.status', 'no_show');

        $feedback = $this->feedbackFor($this->guest);
        $this->setStatus($staff, $feedback, 'confirmed')->assertOk();
        $this->setStatus($staff, $feedback, 'no_show')->assertStatus(422)->assertJsonPath('error.code', 'invalid_transition');
    }

    public function test_declining_and_cancelling_need_a_reason_which_the_guest_can_read(): void
    {
        $staff = $this->staff();
        $request = $this->reservationFor($this->guest);

        $this->setStatus($staff, $request, 'declined')->assertStatus(422)->assertJsonValidationErrors(['reason'], 'error.fields');
        $this->setStatus($staff, $request, 'declined', reason: 'Dapur penuh')->assertOk()->assertJsonPath('data.status', 'declined');

        $this->assertSame('Dapur penuh', RequestMessage::query()->where('request_id', $request->id)->firstOrFail()->text);

        $this->asGuest($this->guest);
        $this->getJson("/api/v1/requests/{$request->public_id}/messages")->assertOk()->assertJsonPath('data.0.text', 'Dapur penuh')
            ->assertJsonPath('meta.request.status', 'declined')->assertJsonPath('meta.request.can_cancel', false);
    }

    public function test_a_stale_version_conflicts_and_a_replayed_key_changes_nothing_twice(): void
    {
        $staff = $this->staff();
        $other = $this->staff();
        $request = $this->reservationFor($this->guest);
        $key = (string) Str::uuid();

        $this->setStatus($staff, $request, 'confirmed', 1, key: $key)->assertOk();
        $this->setStatus($staff, $request, 'confirmed', 1, key: $key)->assertOk()->assertJsonPath('data.version', 2);
        $this->assertSame(1, RequestEvent::query()->where('event_code', 'status_changed')->count());

        // A colleague still looking at version 1 must not overwrite the newer state.
        $this->setStatus($other, $request, 'declined', 1, 'x')->assertStatus(409)->assertJsonPath('error.code', 'version_conflict');
        $this->assertSame('confirmed', $request->refresh()->status->value);

        // The same key with a different target is a misuse.
        $this->setStatus($staff, $request, 'completed', 2, key: $key)->assertStatus(409)->assertJsonPath('error.code', 'idempotency_conflict');
    }

    public function test_an_order_cannot_be_confirmed_while_a_dish_is_sold_out(): void
    {
        $kitchen = $this->staff('kitchen');
        $dish = $this->dish('rendang', 68000);
        $draft = $this->draftFor($this->guest, RequestType::Order, ['fulfilment' => 'pickup', 'requested_time' => '12:00', 'items' => [['menu_item_id' => $dish->id, 'quantity' => 1]], 'notes' => null], ['name' => 'Tamu', 'phone' => '+62 812 000 111']);
        $order = $this->confirmDraft($this->guest, $draft);

        $dish->forceFill(['is_available' => false])->save();
        $this->setStatus($kitchen, $order, 'confirmed')->assertStatus(409)->assertJsonPath('error.code', 'item_unavailable');

        $dish->forceFill(['is_available' => true])->save();
        $this->setStatus($kitchen, $order, 'confirmed')->assertOk();
    }

    public function test_the_guest_cancels_a_pending_or_confirmed_request_once(): void
    {
        $staff = $this->staff();
        $pending = $this->reservationFor($this->guest);
        $confirmed = $this->reservationFor($this->guest, 2);
        $this->setStatus($staff, $confirmed, 'confirmed')->assertOk();
        $this->asGuest($this->guest);

        foreach ([$pending, $confirmed] as $request) {
            $this->postJson("/api/v1/requests/{$request->public_id}/cancel", ['reason' => 'Berubah rencana', 'idempotency_key' => (string) Str::uuid()])
                ->assertOk()->assertJsonPath('data.status', 'cancelled')->assertJsonPath('data.can_cancel', false);
            $this->assertNotNull($request->refresh()->terminal_at);
            $this->postJson("/api/v1/requests/{$request->public_id}/cancel", ['idempotency_key' => (string) Str::uuid()])
                ->assertStatus(409)->assertJsonPath('error.code', 'request_closed');
        }

        $this->assertSame(0, (int) ReservationSlotUsage::query()->sum('booked_seats'));
    }

    public function test_the_cancel_retry_with_the_same_key_returns_the_same_result(): void
    {
        $request = $this->reservationFor($this->guest);
        $this->asGuest($this->guest);
        $key = (string) Str::uuid();

        $this->postJson("/api/v1/requests/{$request->public_id}/cancel", ['idempotency_key' => $key])->assertOk();
        $this->postJson("/api/v1/requests/{$request->public_id}/cancel", ['idempotency_key' => $key])->assertOk()->assertJsonPath('data.status', 'cancelled');

        $this->assertSame(1, RequestEvent::query()->where('event_code', 'cancelled')->count());
    }

    public function test_messages_flow_both_ways_and_internal_notes_never_reach_the_guest(): void
    {
        $staff = $this->staff();
        $request = $this->reservationFor($this->guest);

        $this->staffMessage($staff, $request, 'Kami siapkan meja dekat jendela.')->assertCreated()->assertJsonPath('data.visibility', 'public');
        $this->staffMessage($staff, $request, 'Tamu VIP, jangan bilang.', 'internal')->assertCreated()->assertJsonPath('data.visibility', 'internal');

        $this->asGuest($this->guest);
        $this->postJson("/api/v1/requests/{$request->public_id}/messages", ['text' => 'Terima kasih!', 'idempotency_key' => (string) Str::uuid()])
            ->assertCreated()->assertJsonPath('data.mine', true)->assertJsonPath('data.author_type', 'guest');

        $guestView = $this->getJson("/api/v1/requests/{$request->public_id}/messages")->assertOk();
        $this->assertSame(['Kami siapkan meja dekat jendela.', 'Terima kasih!'], array_column($guestView->json('data'), 'text'));
        $this->assertStringNotContainsString('VIP', $guestView->getContent());

        $staffView = $this->actingAs($staff)->getJson("/api/v1/staff/requests/{$request->public_id}/messages")->assertOk();
        $this->assertCount(3, $staffView->json('data'));
        $this->assertStringContainsString('VIP', $staffView->getContent());

        // The bookings page and the guest card never carry the internal note either.
        $this->asGuest($this->guest);
        $this->get("/bookings/{$request->reference}")->assertOk()->assertDontSee('VIP');
    }

    public function test_the_message_cursor_returns_only_newer_messages(): void
    {
        $staff = $this->staff();
        $request = $this->reservationFor($this->guest);
        $first = $this->staffMessage($staff, $request, 'Satu')->json('data.id');
        $this->staffMessage($staff, $request, 'Dua');
        $this->asGuest($this->guest);

        $this->getJson("/api/v1/requests/{$request->public_id}/messages?after_id={$first}")->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.text', 'Dua');
        $this->getJson("/api/v1/requests/{$request->public_id}/messages?after_id=abc")->assertStatus(422);
    }

    public function test_a_replayed_message_key_does_not_duplicate_the_message(): void
    {
        $request = $this->reservationFor($this->guest);
        $this->asGuest($this->guest);
        $key = (string) Str::uuid();
        $body = ['text' => 'Halo', 'idempotency_key' => $key];

        $created = $this->postJson("/api/v1/requests/{$request->public_id}/messages", $body)->assertCreated();
        $replay = $this->postJson("/api/v1/requests/{$request->public_id}/messages", $body)->assertOk();

        $this->assertSame($created->json('data.id'), $replay->json('data.id'));
        $this->assertSame(1, RequestMessage::query()->count());
        $this->postJson("/api/v1/requests/{$request->public_id}/messages", ['text' => 'Beda', 'idempotency_key' => $key])->assertStatus(409)->assertJsonPath('error.code', 'idempotency_conflict');
    }

    public function test_nobody_can_message_a_closed_request(): void
    {
        $staff = $this->staff();
        $request = $this->reservationFor($this->guest);
        $this->setStatus($staff, $request, 'declined', reason: 'Penuh')->assertOk();

        $this->staffMessage($staff, $request, 'Maaf')->assertStatus(409)->assertJsonPath('error.code', 'request_closed');
        $this->asGuest($this->guest);
        $this->postJson("/api/v1/requests/{$request->public_id}/messages", ['text' => 'Tolong', 'idempotency_key' => (string) Str::uuid()])
            ->assertStatus(409)->assertJsonPath('error.code', 'request_closed');
    }

    public function test_message_text_is_validated_and_stored_as_plain_text(): void
    {
        $request = $this->reservationFor($this->guest);
        $this->asGuest($this->guest);

        $this->postJson("/api/v1/requests/{$request->public_id}/messages", ['text' => '   ', 'idempotency_key' => (string) Str::uuid()])->assertStatus(422);
        $this->postJson("/api/v1/requests/{$request->public_id}/messages", ['text' => str_repeat('x', 5001), 'idempotency_key' => (string) Str::uuid()])->assertStatus(422);
        $this->postJson("/api/v1/requests/{$request->public_id}/messages", ['text' => 'Halo', 'idempotency_key' => 'not-a-uuid'])->assertStatus(422);

        $this->postJson("/api/v1/requests/{$request->public_id}/messages", ['text' => '<script>alert(1)</script>', 'idempotency_key' => (string) Str::uuid()])->assertCreated();
        $this->assertSame('<script>alert(1)</script>', RequestMessage::query()->firstOrFail()->text);
    }

    public function test_staff_assign_a_member_of_the_same_team_only(): void
    {
        $staff = $this->staff('front_of_house');
        $colleague = $this->staff('front_of_house');
        $kitchen = $this->staff('kitchen');
        $request = $this->reservationFor($this->guest);
        $assign = fn (int $assignee, ?int $version = null) => $this->actingAs($staff)->patchJson("/api/v1/staff/requests/{$request->public_id}/assignment", [
            'assignee_id' => $assignee, 'expected_version' => $version ?? $request->refresh()->version, 'idempotency_key' => (string) Str::uuid(),
        ]);

        $assign($kitchen->id)->assertStatus(422)->assertJsonValidationErrors(['assignee_id'], 'error.fields');
        $assign($this->admin()->id)->assertStatus(422);
        $assign($colleague->id)->assertOk()->assertJsonPath('data.assignee.id', $colleague->id)->assertJsonPath('data.version', 2);
        $assign($staff->id, 1)->assertStatus(409)->assertJsonPath('error.code', 'version_conflict');

        $this->assertSame(1, RequestEvent::query()->where('event_code', 'assigned')->count());
    }

    public function test_the_staff_pages_show_contact_and_status_actions(): void
    {
        $staff = $this->staff();
        $request = $this->reservationFor($this->guest);

        $this->actingAs($staff)->get('/admin/requests')->assertOk()->assertSee($request->reference);
        $this->actingAs($staff)->get("/admin/requests/{$request->public_id}")->assertOk()
            ->assertSee('Tamu Uji')->assertSee('+62 812 3456 7890')->assertSee(__('requests.actions.confirm'));
        $this->actingAs($staff)->get('/admin/requests?status=completed')->assertOk()->assertDontSee($request->reference);
        $this->actingAs($staff)->get('/admin/requests?type=reservation&mine=1')->assertOk()->assertDontSee($request->reference);
    }
}
