<?php

namespace Tests\Feature;

use App\Models\GuestSession;
use App\Modules\Identity\Guest\GuestSessions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Support\BuildsRestaurantData;
use Tests\TestCase;

/**
 * Plan 2: anonymous guest sessions are created lazily on the first write,
 * carry no staff rights, and are protected by the honeypot and the limiters.
 */
class GuestSessionTest extends TestCase
{
    use BuildsRestaurantData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();
        $this->freezeClock();
        RateLimiter::clear('guest-session-create');
    }

    /**
     * @return array<string, mixed>
     */
    private function feedbackDraftBody(array $extra = []): array
    {
        return ['type' => 'feedback', 'payload' => $this->feedbackPayload()] + $extra;
    }

    public function test_page_views_never_create_a_guest_session(): void
    {
        foreach (['/', '/menu', '/picks', '/info', '/reservation', '/order', '/bookings'] as $path) {
            $this->get($path)->assertOk();
        }
        $this->get('/api/v1/menu')->assertOk();
        $this->getJson('/api/v1/requests')->assertOk();

        $this->assertDatabaseCount('guest_sessions', 0);
    }

    public function test_the_first_write_creates_one_session_and_later_writes_reuse_it(): void
    {
        $this->postJson('/api/v1/request-drafts', $this->feedbackDraftBody())->assertCreated();
        $this->assertDatabaseCount('guest_sessions', 1);

        $this->postJson('/api/v1/request-drafts', $this->feedbackDraftBody())->assertCreated();
        $this->assertDatabaseCount('guest_sessions', 1);
        $this->assertDatabaseCount('request_drafts', 2);

        $session = GuestSession::query()->firstOrFail();
        $this->assertSame(config('restaurant.default_locale'), $session->locale);
        $this->assertTrue($session->expires_at->isFuture());
    }

    public function test_locale_chosen_before_the_first_write_is_stored_on_the_session(): void
    {
        $this->get('/menu?lang=ja')->assertOk();
        $this->postJson('/api/v1/request-drafts', $this->feedbackDraftBody())->assertCreated();

        $this->assertSame('ja', GuestSession::query()->firstOrFail()->locale);
    }

    public function test_a_valid_table_label_is_kept_and_an_invalid_one_is_ignored(): void
    {
        $this->get('/?table=A3')->assertOk()->assertSee('A3');
        $this->postJson('/api/v1/request-drafts', $this->feedbackDraftBody())->assertCreated();
        $this->assertSame('A3', GuestSession::query()->firstOrFail()->table_label);

        GuestSession::query()->delete();
        $this->flushSession();

        $this->get('/?table='.urlencode('<script>alert(1)</script>'))->assertOk()->assertDontSee('alert(1)', false);
        $this->postJson('/api/v1/request-drafts', $this->feedbackDraftBody())->assertCreated();
        $this->assertNull(GuestSession::query()->firstOrFail()->table_label);
    }

    public function test_table_label_validation_rules(): void
    {
        foreach (['A3', 'T-12', 'garden-5'] as $valid) {
            $this->assertTrue(GuestSessions::isValidTableLabel($valid), $valid);
        }
        foreach (['', 'a b', '<b>', str_repeat('x', 21), 'ä1', null, 5] as $invalid) {
            $this->assertFalse(GuestSessions::isValidTableLabel($invalid), (string) $invalid);
        }
    }

    public function test_an_expired_session_row_is_replaced_on_the_next_write(): void
    {
        $this->postJson('/api/v1/request-drafts', $this->feedbackDraftBody())->assertCreated();
        $first = GuestSession::query()->firstOrFail();
        $first->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->postJson('/api/v1/request-drafts', $this->feedbackDraftBody())->assertCreated();

        $this->assertDatabaseCount('guest_sessions', 2);
        $this->assertNotSame($first->id, session(GuestSessions::SESSION_KEY));
    }

    public function test_a_staff_member_browsing_guest_pages_is_an_ordinary_guest(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->postJson('/api/v1/request-drafts', $this->feedbackDraftBody())->assertCreated();

        // The draft belongs to an anonymous guest session, not to the staff account.
        $this->assertDatabaseCount('guest_sessions', 1);
        $this->assertDatabaseMissing('request_drafts', ['guest_session_id' => null]);
        $this->actingAs($admin)->getJson('/api/v1/requests')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_guest_sessions_cannot_reach_the_staff_area(): void
    {
        $this->postJson('/api/v1/request-drafts', $this->feedbackDraftBody())->assertCreated();

        $this->get('/admin')->assertRedirect(route('login'));
        $this->get('/admin/requests')->assertRedirect(route('login'));
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
        $this->getJson('/api/v1/admin/readiness')->assertUnauthorized();
        $this->getJson('/api/v1/staff/requests/'.fake()->uuid().'/messages')->assertUnauthorized();
    }

    public function test_a_filled_honeypot_is_rejected_before_any_session_exists(): void
    {
        $this->postJson('/api/v1/request-drafts', $this->feedbackDraftBody(['contact_website' => 'https://spam.example']))
            ->assertStatus(422);
        $this->postJson('/api/v1/conversations', ['locale' => 'id', 'scene' => 'menu', 'contact_website' => 'x'])
            ->assertStatus(422);

        $this->assertDatabaseCount('guest_sessions', 0);
        $this->assertDatabaseCount('request_drafts', 0);
    }

    public function test_the_session_creation_limiter_applies_per_ip(): void
    {
        $responses = [];
        for ($i = 0; $i < 35; $i++) {
            $responses[] = $this->postJson('/api/v1/request-drafts', $this->feedbackDraftBody())->getStatusCode();
        }

        $this->assertContains(429, $responses);
        $this->assertSame(201, $responses[0]);
    }

    public function test_lookup_limiter_is_per_ip_and_returns_retry_after(): void
    {
        $last = null;
        for ($i = 0; $i < 11; $i++) {
            $last = $this->postJson('/api/v1/requests/lookup', ['reference' => 'RST-20261007-ABCDEFGH', 'contact' => 'x@example.test']);
        }

        $last->assertStatus(429)->assertJsonPath('error.code', 'too_many_requests');
        $this->assertNotNull($last->headers->get('Retry-After'));
    }
}
