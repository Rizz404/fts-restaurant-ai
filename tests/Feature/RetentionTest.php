<?php

namespace Tests\Feature;

use App\Models\AiTurn;
use App\Models\AuditEvent;
use App\Models\Conversation;
use App\Models\GuestRequest;
use App\Models\GuestSession;
use App\Models\OperationReceipt;
use App\Models\RequestDraft;
use App\Models\RequestEvent;
use App\Models\RequestMessage;
use App\Models\ReservationSlotUsage;
use App\Models\User;
use App\Modules\Conversation\Actions\StartConversation;
use App\Modules\Experience\Enums\SceneCode;
use App\Modules\Identity\Guest\GuestContext;
use App\Modules\Operations\Retention\PurgeExpiredData;
use App\Modules\Requests\Actions\TransitionRequest;
use App\Modules\Requests\Enums\RequestStatus;
use App\Modules\Requests\Enums\RequestType;
use App\Notifications\RequestActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\BuildsRestaurantData;
use Tests\TestCase;

/**
 * Plan 8: retention. Closed requests (and their contact data) go after 180
 * days, open ones never, sessions take their conversations but not their
 * requests, and the circular draft/request link is cleaned in a safe order.
 */
class RetentionTest extends TestCase
{
    use BuildsRestaurantData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();
        $this->freezeClock();
    }

    private function purge(): array
    {
        return app(PurgeExpiredData::class)->run();
    }

    private function closedRequest(GuestContext $guest, string $closedAgo = '181 days'): GuestRequest
    {
        $request = $this->reservationFor($guest);
        app(TransitionRequest::class)->handle($this->staff(), $request, RequestStatus::Declined, 1, 'Penuh', (string) Str::uuid());
        $request->refresh()->forceFill(['terminal_at' => now()->sub($closedAgo)])->save();
        RequestMessage::query()->create(['request_id' => $request->id, 'author_type' => 'guest', 'visibility' => 'public', 'text' => 'Pesan tamu', 'created_at' => now()->sub($closedAgo)]);

        return $request;
    }

    public function test_a_closed_request_is_purged_with_everything_that_belongs_to_it(): void
    {
        [, $guest] = $this->makeGuest();
        $request = $this->closedRequest($guest);
        $draftId = $request->draft_id;
        $messageId = RequestMessage::query()->where('request_id', $request->id)->firstOrFail()->id;
        // A notification and a message receipt that reference it.
        $this->staff('front_of_house')->notifications()->count();
        $user = User::query()->whereHas('staffTeams')->firstOrFail();
        $user->notify(new RequestActivity($request->public_id, 'request_created'));
        OperationReceipt::query()->create(['actor_type' => 'guest_session', 'actor_id' => $guest->guestSessionId, 'operation_type' => 'request.message', 'idempotency_key' => (string) Str::uuid(), 'fingerprint' => 'f', 'resource_type' => 'request_message', 'resource_public_id' => (string) $messageId, 'created_at' => now()]);

        $this->assertSame(1, app(PurgeExpiredData::class)->counts()['closed_requests']);
        $deleted = $this->purge();

        $this->assertSame(1, $deleted['closed_requests']);
        $this->assertNull(GuestRequest::query()->find($request->id));
        $this->assertNull(RequestDraft::query()->find($draftId));
        $this->assertSame(0, RequestMessage::query()->count());
        $this->assertSame(0, RequestEvent::query()->count());
        $this->assertSame(0, DB::table('notifications')->where('data', 'like', '%'.$request->public_id.'%')->count());
        $this->assertSame(0, OperationReceipt::query()->where('resource_public_id', $request->public_id)->orWhere('resource_public_id', (string) $messageId)->count());
        $this->assertSame(0, (int) ReservationSlotUsage::query()->sum('booked_seats'), 'Seats were released when the request closed.');
    }

    public function test_the_contact_details_leave_with_the_request(): void
    {
        [, $guest] = $this->makeGuest();
        $this->closedRequest($guest);

        $this->purge();

        $this->assertSame(0, GuestRequest::query()->where('contact_phone', '+62 812 3456 7890')->count());
        $this->assertSame(0, RequestDraft::query()->where('contact_phone', '+62 812 3456 7890')->count());
    }

    public function test_young_closed_requests_and_open_requests_of_any_age_are_kept(): void
    {
        [, $guest] = $this->makeGuest();
        $young = $this->closedRequest($guest, '100 days');
        $pending = $this->reservationFor($guest, 2);
        $pending->forceFill(['confirmed_at' => now()->subYears(2), 'created_at' => now()->subYears(2)])->save();
        $confirmed = $this->reservationFor($guest, 2);
        app(TransitionRequest::class)->handle($this->staff(), $confirmed, RequestStatus::Confirmed, 1, null, (string) Str::uuid());
        $confirmed->forceFill(['confirmed_at' => now()->subYears(2)])->save();

        $this->assertSame(0, $this->purge()['closed_requests']);
        $this->assertSame(3, GuestRequest::query()->count());
        $this->assertNotNull($young->refresh());
    }

    public function test_unconsumed_drafts_are_removed_after_a_day_of_grace(): void
    {
        [, $guest] = $this->makeGuest();
        $old = $this->draftFor($guest, RequestType::Feedback, $this->feedbackPayload());
        $recent = $this->draftFor($guest, RequestType::Feedback, $this->feedbackPayload('Baru'));
        $old->forceFill(['expires_at' => now()->subHours(25)])->save();
        $recent->forceFill(['expires_at' => now()->subHours(23)])->save();
        $confirmed = $this->feedbackFor($guest);
        RequestDraft::query()->whereKey($confirmed->draft_id)->update(['expires_at' => now()->subDays(30)]);

        $deleted = $this->purge();

        $this->assertSame(1, $deleted['unconsumed_drafts']);
        $this->assertNull(RequestDraft::query()->find($old->id));
        $this->assertNotNull(RequestDraft::query()->find($recent->id));
        $this->assertNotNull(RequestDraft::query()->find($confirmed->draft_id), 'A consumed draft follows its request, not the grace rule.');
    }

    public function test_old_conversations_go_with_turns_and_messages_but_keep_their_requests_and_drafts(): void
    {
        [, $guest] = $this->makeGuest();
        $old = app(StartConversation::class)->handle($guest, 'id', SceneCode::Menu);
        $fresh = app(StartConversation::class)->handle($guest, 'id', SceneCode::Menu);
        $turn = AiTurn::query()->create([
            'public_id' => (string) Str::uuid(), 'conversation_id' => $old->id, 'client_turn_id' => (string) Str::uuid(), 'request_fingerprint' => str_repeat('d', 64),
            'status' => 'completed', 'locale' => 'id', 'context_snapshot' => [], 'started_at' => now(), 'deadline_at' => now(), 'prompt_version' => 'x',
        ]);
        $draft = $this->draftFor($guest, RequestType::Feedback, $this->feedbackPayload());
        $draft->forceFill(['conversation_id' => $old->id, 'originating_turn_id' => $turn->id])->save();
        $old->forceFill(['last_activity_at' => now()->subDays(31)])->save();
        $fresh->forceFill(['last_activity_at' => now()->subDays(29)])->save();

        $deleted = $this->purge();

        $this->assertSame(1, $deleted['conversations']);
        $this->assertNull(Conversation::query()->find($old->id));
        $this->assertNull(AiTurn::query()->find($turn->id));
        $this->assertNotNull(Conversation::query()->find($fresh->id));
        $draft->refresh();
        $this->assertNull($draft->conversation_id);
        $this->assertNull($draft->originating_turn_id);
    }

    public function test_an_expired_guest_session_takes_its_conversations_but_keeps_its_requests(): void
    {
        [$session, $guest] = $this->makeGuest();
        $conversation = app(StartConversation::class)->handle($guest, 'id', SceneCode::Menu);
        $request = $this->reservationFor($guest);
        $session->forceFill(['last_seen_at' => now()->subDays(31)])->save();
        [$active] = $this->makeGuest();

        $deleted = $this->purge();

        $this->assertSame(1, $deleted['guest_sessions']);
        $this->assertNull(GuestSession::query()->find($session->id));
        $this->assertNotNull(GuestSession::query()->find($active->id));
        $this->assertNull(Conversation::query()->find($conversation->id));
        $this->assertNotNull(GuestRequest::query()->find($request->id), 'The request outlives the browser session.');
        $this->assertNull($request->refresh()->guest_session_id);
        $this->assertNull(RequestDraft::query()->find($request->draft_id)->guest_session_id);
    }

    public function test_the_order_requests_then_drafts_then_sessions_never_trips_a_foreign_key(): void
    {
        [$session, $guest] = $this->makeGuest();
        $this->closedRequest($guest);
        $this->draftFor($guest, RequestType::Feedback, $this->feedbackPayload())->forceFill(['expires_at' => now()->subDays(3)])->save();
        $session->forceFill(['last_seen_at' => now()->subDays(40)])->save();

        $deleted = $this->purge();

        $this->assertSame(1, $deleted['closed_requests']);
        $this->assertSame(1, $deleted['unconsumed_drafts']);
        $this->assertSame(1, $deleted['guest_sessions']);
        $this->assertSame(0, GuestRequest::query()->count() + RequestDraft::query()->count() + GuestSession::query()->count());
    }

    public function test_usage_audit_and_receipt_rows_have_their_own_retention(): void
    {
        ReservationSlotUsage::query()->create(['date' => now()->subDays(61)->format('Y-m-d'), 'slot_start' => '10:00', 'booked_seats' => 2]);
        ReservationSlotUsage::query()->create(['date' => now()->subDays(59)->format('Y-m-d'), 'slot_start' => '10:00', 'booked_seats' => 2]);
        DB::table('assistant_usage')->insert([['usage_date' => now()->subDays(91)->format('Y-m-d'), 'guest_session_id' => null, 'turns' => 5], ['usage_date' => now()->subDays(89)->format('Y-m-d'), 'guest_session_id' => null, 'turns' => 5]]);
        AuditEvent::query()->create(['actor_id' => null, 'action' => 'x', 'subject_type' => 'x', 'subject_id' => '1', 'occurred_at' => now()->subDays(366)]);
        AuditEvent::query()->create(['actor_id' => null, 'action' => 'x', 'subject_type' => 'x', 'subject_id' => '2', 'occurred_at' => now()->subDays(364)]);
        foreach ([366, 364] as $days) {
            OperationReceipt::query()->create(['actor_type' => 'user', 'actor_id' => 1, 'operation_type' => 'x', 'idempotency_key' => (string) Str::uuid(), 'fingerprint' => 'f', 'resource_type' => 'request', 'resource_public_id' => (string) Str::uuid(), 'created_at' => now()->subDays($days)]);
        }

        $deleted = $this->purge();

        $this->assertSame(['slot_usage' => 1, 'assistant_usage' => 1, 'audit_events' => 1, 'receipts' => 1], array_intersect_key($deleted, array_flip(['slot_usage', 'assistant_usage', 'audit_events', 'receipts'])));
        $this->assertSame(1, ReservationSlotUsage::query()->count());
        $this->assertSame(1, DB::table('assistant_usage')->count());
        $this->assertSame(1, AuditEvent::query()->count());
        $this->assertSame(1, OperationReceipt::query()->count());
    }

    public function test_the_purge_works_in_batches(): void
    {
        GuestSession::factory()->count(PurgeExpiredData::BATCH * 2 + 50)->create(['last_seen_at' => now()->subDays(40)]);
        GuestSession::factory()->count(3)->create();

        $this->assertSame(PurgeExpiredData::BATCH * 2 + 50, $this->purge()['guest_sessions']);
        $this->assertSame(3, GuestSession::query()->count());
    }

    public function test_the_command_dry_run_counts_without_deleting_and_prints_no_personal_data(): void
    {
        [, $guest] = $this->makeGuest();
        $request = $this->closedRequest($guest);

        $this->artisan('restaurant:purge-data', ['--dry-run' => true])
            ->expectsOutputToContain('closed_requests')
            ->doesntExpectOutputToContain('Tamu Uji')
            ->doesntExpectOutputToContain($request->reference)
            ->assertSuccessful();

        $this->assertNotNull(GuestRequest::query()->find($request->id));

        $this->artisan('restaurant:purge-data')->assertSuccessful();
        $this->assertNull(GuestRequest::query()->find($request->id));
    }

    public function test_purging_twice_is_harmless(): void
    {
        [, $guest] = $this->makeGuest();
        $this->closedRequest($guest);

        $this->purge();
        $second = $this->purge();

        $this->assertSame(0, array_sum($second));
    }

    public function test_users_are_never_deleted_by_the_purge(): void
    {
        $this->staff();
        $this->admin();

        $this->purge();

        $this->assertSame(2, User::query()->count());
    }
}
