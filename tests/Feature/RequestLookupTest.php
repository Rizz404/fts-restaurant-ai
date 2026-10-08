<?php

namespace Tests\Feature;

use App\Models\GuestRequest;
use App\Modules\Identity\Guest\GuestContext;
use App\Modules\Identity\Guest\GuestSessions;
use App\Modules\Identity\Guest\SessionGrants;
use App\Modules\Requests\Enums\RequestType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\BuildsRestaurantData;
use Tests\TestCase;

/**
 * Plan 6: a request opens on another device only with its reference plus the
 * contact it was booked with. Failures are uniform and locked per reference,
 * and a reference alone never reveals whether a request exists.
 */
class RequestLookupTest extends TestCase
{
    use BuildsRestaurantData;
    use RefreshDatabase;

    private GuestContext $owner;

    private GuestRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();
        $this->freezeClock();
        $this->withoutMiddleware(ThrottleRequests::class);
        RateLimiter::clear('guest-session-create');
        Cache::flush();

        [, $this->owner] = $this->makeGuest();
        $draft = $this->draftFor($this->owner, RequestType::Reservation, $this->reservationPayload(), ['name' => 'Tamu Uji', 'phone' => '+62 812 3456 7890', 'email' => 'Tamu.Uji@Example.test']);
        $this->request = $this->confirmDraft($this->owner, $draft);
        $this->flushSession();
    }

    private function lookup(string $reference, string $contact): TestResponse
    {
        return $this->postJson('/api/v1/requests/lookup', ['reference' => $reference, 'contact' => $contact]);
    }

    public function test_the_right_reference_and_phone_open_the_request_for_this_browser(): void
    {
        $this->getJson("/api/v1/requests/{$this->request->public_id}")->assertNotFound();

        $this->lookup($this->request->reference, '0812-3456-7890')->assertNotFound(); // 08… is not the stored +62 number
        $this->lookup($this->request->reference, '+62 812 3456 7890')->assertOk()->assertJsonPath('data.reference', $this->request->reference);

        $this->getJson("/api/v1/requests/{$this->request->public_id}")->assertOk();
        $this->getJson("/api/v1/requests/{$this->request->public_id}/messages")->assertOk();
        $this->getJson('/api/v1/requests')->assertOk()->assertJsonPath('data.0.reference', $this->request->reference);
        $this->postJson("/api/v1/requests/{$this->request->public_id}/messages", ['text' => 'Halo', 'idempotency_key' => (string) Str::uuid()])->assertCreated();
    }

    public function test_the_phone_matches_by_digits_and_the_email_case_insensitively(): void
    {
        $this->lookup(strtolower($this->request->reference), '(62) 812-3456 7890')->assertOk();
        $this->flushSession();
        $this->lookup(' '.$this->request->reference.' ', 'TAMU.UJI@EXAMPLE.TEST')->assertOk();
    }

    public function test_every_failure_looks_the_same_and_grants_nothing(): void
    {
        $responses = [
            'wrong contact' => $this->lookup($this->request->reference, 'someone@example.test'),
            'unknown reference' => $this->lookup('RST-20261007-ZZZZZZZZ', '+62 812 3456 7890'),
            'malformed reference' => $this->lookup('nope', '+62 812 3456 7890'),
            'short phone' => $this->lookup($this->request->reference, '812'),
        ];

        $first = null;
        foreach ($responses as $case => $response) {
            $response->assertNotFound()->assertJsonPath('error.code', 'lookup_failed');
            $body = $response->json('error');
            unset($body['fields']);
            $first ??= $body;
            $this->assertSame($first, $body, $case);
        }

        $this->getJson("/api/v1/requests/{$this->request->public_id}")->assertNotFound();
        $this->assertSame([], session(SessionGrants::SESSION_KEY, []));
        // Failed lookups create no guest session (only the owner fixture exists).
        $this->assertDatabaseCount('guest_sessions', 1);
    }

    public function test_five_failures_lock_the_reference_even_for_the_right_contact(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->lookup($this->request->reference, 'wrong@example.test')->assertNotFound();
        }

        $this->lookup($this->request->reference, '+62 812 3456 7890')->assertNotFound()->assertJsonPath('error.code', 'lookup_failed');

        // After the lock window the owner can open it again; other references are unaffected.
        $this->travel(16)->minutes();
        $this->lookup($this->request->reference, '+62 812 3456 7890')->assertOk();
    }

    public function test_a_session_keeps_at_most_twenty_grants(): void
    {
        $references = [];
        foreach (range(1, 22) as $i) {
            [, $guest] = $this->makeGuest();
            $request = $this->confirmDraft($guest, $this->draftFor($guest, RequestType::Feedback, $this->feedbackPayload("Feedback {$i}"), ['name' => 'T', 'phone' => sprintf('+62 811 0000 %04d', $i)]));
            $references[$i] = [$request->reference, sprintf('+62 811 0000 %04d', $i)];
        }

        $this->flushSession();
        foreach ($references as [$reference, $phone]) {
            $this->lookup($reference, $phone)->assertOk();
        }

        $this->assertCount(SessionGrants::MAX, session(SessionGrants::SESSION_KEY));
        $this->getJson('/api/v1/requests')->assertOk()->assertJsonCount(SessionGrants::MAX, 'data');
    }

    public function test_a_reference_page_never_reveals_whether_the_reference_exists(): void
    {
        $existing = $this->get("/bookings/{$this->request->reference}")->assertOk();
        $missing = $this->get('/bookings/RST-20261007-ZZZZZZZZ')->assertOk();
        $garbage = $this->get('/bookings/not-a-reference')->assertOk();

        foreach ([$existing, $missing, $garbage] as $response) {
            $response->assertSee(__('requests.lookup_needed'))->assertDontSee('+62 812 3456 7890')->assertDontSee('Tamu Uji');
        }
        $this->assertStringNotContainsString($this->request->public_id, $existing->getContent());
    }

    public function test_the_reference_page_shows_the_request_after_a_successful_lookup(): void
    {
        $this->lookup($this->request->reference, '+62 812 3456 7890')->assertOk();

        $page = $this->get("/bookings/{$this->request->reference}")->assertOk();

        $page->assertDontSee(__('requests.lookup_needed'))->assertSee($this->request->reference)->assertSee($this->request->public_id);
        $this->assertStringNotContainsString('+62 812 3456 7890', $page->getContent(), 'The guest page does not echo contact data.');
    }

    public function test_the_owner_session_sees_its_own_requests_without_a_lookup(): void
    {
        $this->withSession([GuestSessions::SESSION_KEY => $this->owner->guestSessionId]);

        $this->getJson('/api/v1/requests')->assertOk()->assertJsonPath('data.0.reference', $this->request->reference);
        $this->get('/bookings')->assertOk()->assertSee($this->request->reference);
        $this->get("/bookings/{$this->request->reference}")->assertOk()->assertSee($this->request->public_id);
    }

    public function test_requests_of_other_guests_cannot_be_opened_by_uuid(): void
    {
        [, $stranger] = $this->makeGuest();
        $this->withSession([GuestSessions::SESSION_KEY => $stranger->guestSessionId]);

        $this->getJson("/api/v1/requests/{$this->request->public_id}")->assertNotFound();
        $this->getJson("/api/v1/requests/{$this->request->public_id}/messages")->assertNotFound();
        $this->postJson("/api/v1/requests/{$this->request->public_id}/cancel", ['idempotency_key' => (string) Str::uuid()])->assertNotFound();
        $this->getJson('/api/v1/requests')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_lookup_validates_its_input_and_a_filled_honeypot_is_rejected(): void
    {
        $this->postJson('/api/v1/requests/lookup', ['reference' => 'x'])->assertStatus(422);
        $this->postJson('/api/v1/requests/lookup', ['reference' => $this->request->reference, 'contact' => '+62 812 3456 7890', 'contact_website' => 'spam'])->assertStatus(422);
    }
}
