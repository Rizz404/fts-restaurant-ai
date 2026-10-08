<?php

namespace Tests\Feature;

use App\Models\GuestRequest;
use App\Modules\Identity\Guest\GuestContext;
use App\Modules\Identity\Guest\GuestSessions;
use App\Notifications\RequestActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\Support\BuildsRestaurantData;
use Tests\TestCase;

/**
 * Plan 6: staff notifications are written inside the request transaction,
 * go only to the people who handle the request, and hold no guest content.
 */
class RequestNotificationTest extends TestCase
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

    private function asGuest(): void
    {
        auth()->logout();
        $this->flushSession();
        $this->withSession([GuestSessions::SESSION_KEY => $this->guest->guestSessionId]);
    }

    public function test_a_new_request_notifies_its_team_but_not_other_teams_or_admins(): void
    {
        $foh = $this->staff('front_of_house');
        $foh2 = $this->staff('front_of_house');
        $kitchen = $this->staff('kitchen');
        $admin = $this->admin();
        $inactive = $this->staff('front_of_house');
        $inactive->forceFill(['is_active' => false])->save();

        $request = $this->reservationFor($this->guest);

        foreach ([$foh, $foh2] as $user) {
            $this->assertSame(1, $user->notifications()->count());
            $this->assertSame(['request_public_id' => $request->public_id, 'event_code' => RequestActivity::CREATED], $user->notifications()->firstOrFail()->data);
        }
        foreach ([$kitchen, $admin, $inactive] as $user) {
            $this->assertSame(0, $user->notifications()->count());
        }
    }

    public function test_a_guest_message_goes_to_the_assignee_or_to_the_team_when_nobody_is_assigned(): void
    {
        $assignee = $this->staff('front_of_house');
        $colleague = $this->staff('front_of_house');
        $request = $this->reservationFor($this->guest);
        $assignee->notifications()->delete();
        $colleague->notifications()->delete();

        $this->asGuest();
        $this->postJson("/api/v1/requests/{$request->public_id}/messages", ['text' => 'Halo tim', 'idempotency_key' => (string) Str::uuid()])->assertCreated();
        $this->assertSame(1, $assignee->notifications()->count());
        $this->assertSame(1, $colleague->notifications()->count());

        $this->actingAs($assignee)->patchJson("/api/v1/staff/requests/{$request->public_id}/assignment", ['assignee_id' => $assignee->id, 'expected_version' => 1, 'idempotency_key' => (string) Str::uuid()])->assertOk();
        $assignee->notifications()->delete();
        $colleague->notifications()->delete();

        $this->asGuest();
        $this->postJson("/api/v1/requests/{$request->public_id}/messages", ['text' => 'Sudah ada yang menangani?', 'idempotency_key' => (string) Str::uuid()])->assertCreated();
        $this->assertSame(1, $assignee->notifications()->count());
        $this->assertSame(0, $colleague->notifications()->count());
        $this->assertSame(RequestActivity::GUEST_MESSAGE, $assignee->notifications()->firstOrFail()->data['event_code']);
    }

    public function test_a_guest_cancellation_notifies_the_handlers(): void
    {
        $foh = $this->staff('front_of_house');
        $request = $this->reservationFor($this->guest);
        $foh->notifications()->delete();

        $this->asGuest();
        $this->postJson("/api/v1/requests/{$request->public_id}/cancel", ['idempotency_key' => (string) Str::uuid()])->assertOk();

        $this->assertSame(RequestActivity::GUEST_CANCELLED, $foh->notifications()->firstOrFail()->data['event_code']);
    }

    public function test_notifications_never_contain_contact_details_or_message_text(): void
    {
        $foh = $this->staff('front_of_house');
        $request = $this->reservationFor($this->guest);
        $this->asGuest();
        $this->postJson("/api/v1/requests/{$request->public_id}/messages", ['text' => 'Rahasia pesan tamu', 'idempotency_key' => (string) Str::uuid()])->assertCreated();

        $stored = json_encode($foh->notifications()->get()->pluck('data'));
        $this->assertStringNotContainsString('Rahasia', $stored);
        $this->assertStringNotContainsString('Tamu Uji', $stored);
        $this->assertStringNotContainsString('812', $stored);

        $api = $this->actingAs($foh)->getJson('/api/v1/notifications')->assertOk();
        $this->assertStringNotContainsString('Rahasia', $api->getContent());
        $this->assertStringNotContainsString('+62', $api->getContent());
    }

    public function test_the_api_lists_own_notifications_with_label_reference_and_path_and_marks_them_read(): void
    {
        $foh = $this->staff('front_of_house');
        $other = $this->staff('front_of_house');
        $request = $this->reservationFor($this->guest);

        $list = $this->actingAs($foh)->getJson('/api/v1/notifications')->assertOk();

        $list->assertJsonPath('meta.unread', 1)
            ->assertJsonPath('data.0.label', __('requests.notifications.events.request_created', [], 'id'))
            ->assertJsonPath('data.0.reference', $request->reference)
            ->assertJsonPath('data.0.path', "/admin/requests/{$request->public_id}")
            ->assertJsonPath('data.0.read', false);

        $id = $list->json('data.0.id');
        // Another staff member cannot touch it.
        $this->actingAs($this->staff('kitchen'))->patchJson("/api/v1/notifications/{$id}/read")->assertNotFound();
        $this->actingAs($foh)->patchJson("/api/v1/notifications/{$id}/read")->assertOk();
        $this->actingAs($foh)->getJson('/api/v1/notifications')->assertJsonPath('meta.unread', 0)->assertJsonPath('data.0.read', true);
        $this->actingAs($other)->getJson('/api/v1/notifications')->assertJsonPath('meta.unread', 1);
    }

    public function test_a_notification_for_a_request_the_user_can_no_longer_reach_has_no_path(): void
    {
        $user = $this->staff('front_of_house');
        $request = $this->reservationFor($this->guest);

        GuestRequest::query()->whereKey($request->id)->delete();

        $item = $this->actingAs($user)->getJson('/api/v1/notifications')->assertOk()->json('data.0');
        $this->assertNull($item['path']);
        $this->assertNull($item['reference']);
    }

    public function test_the_bell_is_only_part_of_the_page_for_staff_with_a_team(): void
    {
        $this->actingAs($this->staff('kitchen'))->get('/admin')->assertOk()->assertSee('notificationBell', false);
        $this->actingAs($this->admin())->get('/admin')->assertOk()->assertDontSee('notificationBell', false);
    }
}
