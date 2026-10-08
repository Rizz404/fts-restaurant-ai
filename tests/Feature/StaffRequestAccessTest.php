<?php

namespace Tests\Feature;

use App\Models\GuestRequest;
use App\Models\StaffTeam;
use App\Models\User;
use App\Modules\Identity\Actions\ProvisionUser;
use App\Modules\Identity\Guest\GuestContext;
use App\Modules\Requests\Enums\RequestType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\Support\BuildsRestaurantData;
use Tests\TestCase;

/**
 * Plan 6: staff only handle requests of the teams they belong to. The admin
 * role alone grants nothing, other teams' requests are 404, and a team change
 * takes effect immediately.
 */
class StaffRequestAccessTest extends TestCase
{
    use BuildsRestaurantData;
    use RefreshDatabase;

    private GuestContext $guest;

    private GuestRequest $reservation;

    private GuestRequest $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();
        $this->freezeClock();
        $this->withoutMiddleware(ThrottleRequests::class);
        RateLimiter::clear('guest-session-create');
        [, $this->guest] = $this->makeGuest();

        $dish = $this->dish('rendang', 68000);
        $this->reservation = $this->reservationFor($this->guest);
        $this->order = $this->confirmDraft($this->guest, $this->draftFor($this->guest, RequestType::Order, [
            'fulfilment' => 'pickup', 'requested_time' => '12:00', 'items' => [['menu_item_id' => $dish->id, 'quantity' => 1]], 'notes' => null,
        ], ['name' => 'Tamu', 'phone' => '+62 812 000 111']));
    }

    public function test_each_team_sees_only_its_own_requests(): void
    {
        $foh = $this->staff('front_of_house');
        $kitchen = $this->staff('kitchen');

        $this->actingAs($foh)->get('/admin/requests')->assertOk()->assertSee($this->reservation->reference)->assertDontSee($this->order->reference);
        $this->actingAs($kitchen)->get('/admin/requests')->assertOk()->assertSee($this->order->reference)->assertDontSee($this->reservation->reference);
    }

    public function test_a_request_of_another_team_is_not_found_on_every_surface(): void
    {
        $kitchen = $this->staff('kitchen');
        $path = "/api/v1/staff/requests/{$this->reservation->public_id}";

        $this->actingAs($kitchen)->get("/admin/requests/{$this->reservation->public_id}")->assertNotFound();
        $this->actingAs($kitchen)->getJson("{$path}/messages")->assertNotFound();
        $this->actingAs($kitchen)->postJson("{$path}/messages", ['text' => 'x', 'visibility' => 'public', 'idempotency_key' => (string) Str::uuid()])->assertNotFound();
        $this->actingAs($kitchen)->patchJson("{$path}/status", ['status' => 'confirmed', 'expected_version' => 1, 'idempotency_key' => (string) Str::uuid()])->assertNotFound();
        $this->actingAs($kitchen)->patchJson("{$path}/assignment", ['assignee_id' => $kitchen->id, 'expected_version' => 1, 'idempotency_key' => (string) Str::uuid()])->assertNotFound();

        $this->assertSame('pending', $this->reservation->refresh()->status->value);
    }

    public function test_an_admin_editor_or_team_less_staff_handles_no_requests(): void
    {
        foreach ([$this->admin(), $this->editor(), $this->makeUser(['staff']), $this->makeUser(['admin', 'editor'])] as $user) {
            $this->actingAs($user)->get('/admin/requests')->assertForbidden();
            $this->actingAs($user)->get("/admin/requests/{$this->reservation->public_id}")->assertForbidden();
            $this->actingAs($user)->getJson("/api/v1/staff/requests/{$this->reservation->public_id}/messages")->assertForbidden();
        }
    }

    public function test_a_staff_member_of_two_teams_sees_both(): void
    {
        $both = $this->makeUser(['staff'], ['front_of_house', 'kitchen']);

        $this->actingAs($both)->get('/admin/requests')->assertOk()->assertSee($this->reservation->reference)->assertSee($this->order->reference);
    }

    public function test_changing_a_team_membership_takes_effect_on_the_next_request(): void
    {
        $user = $this->staff('front_of_house');
        $this->actingAs($user)->get("/admin/requests/{$this->reservation->public_id}")->assertOk();

        ProvisionUser::syncAccess($user, ['roles' => ['staff'], 'staff_teams' => ['kitchen']]);

        $this->actingAs($user->refresh())->get("/admin/requests/{$this->reservation->public_id}")->assertNotFound();
    }

    public function test_a_deactivated_team_hides_its_requests(): void
    {
        $foh = $this->staff('front_of_house');
        StaffTeam::query()->where('code', 'front_of_house')->update(['is_active' => false]);

        // With its only team inactive the member handles no team at all, so the panel itself is closed.
        $this->actingAs($foh)->get("/admin/requests/{$this->reservation->public_id}")->assertForbidden();
        $this->actingAs($foh)->get('/admin/requests')->assertForbidden();
    }

    public function test_deactivated_staff_are_signed_out_of_the_panel(): void
    {
        $foh = $this->staff('front_of_house');
        $foh->forceFill(['is_active' => false])->save();

        $this->actingAs($foh)->get('/admin/requests')->assertRedirect(route('login'));
        $this->actingAs($foh)->getJson("/api/v1/staff/requests/{$this->reservation->public_id}/messages")->assertUnauthorized();
    }

    public function test_staff_cannot_reach_guest_only_endpoints_as_staff(): void
    {
        $foh = $this->staff('front_of_house');

        // As a visitor on a guest endpoint a staff member is just another guest: the request is not theirs.
        $this->actingAs($foh)->getJson("/api/v1/requests/{$this->reservation->public_id}")->assertNotFound();
        $this->actingAs($foh)->getJson("/api/v1/requests/{$this->reservation->public_id}/messages")->assertNotFound();
        $this->actingAs($foh)->postJson("/api/v1/requests/{$this->reservation->public_id}/cancel", ['idempotency_key' => (string) Str::uuid()])->assertNotFound();
    }

    public function test_the_today_panel_shows_seats_only_to_front_of_house(): void
    {
        $foh = $this->staff('front_of_house');
        $kitchen = $this->staff('kitchen');

        $this->actingAs($foh)->get('/admin/requests')->assertOk()->assertSee(__('requests.today_title'));
        $this->actingAs($kitchen)->get('/admin/requests')->assertOk()->assertDontSee(__('requests.today_title'));
    }

    public function test_status_buttons_follow_the_current_status(): void
    {
        $foh = $this->staff('front_of_house');
        $page = fn () => $this->actingAs($foh)->get("/admin/requests/{$this->reservation->public_id}");

        $page()->assertSee(__('requests.actions.confirm'))->assertSee(__('requests.actions.decline'))->assertDontSee(__('requests.actions.no_show'));

        $this->actingAs($foh)->patchJson("/api/v1/staff/requests/{$this->reservation->public_id}/status", ['status' => 'confirmed', 'expected_version' => 1, 'idempotency_key' => (string) Str::uuid()])->assertOk();
        $page()->assertSee(__('requests.actions.complete'))->assertSee(__('requests.actions.no_show'))->assertDontSee(__('requests.actions.decline'));
    }

    public function test_the_staff_user_model_scope_finds_team_members_for_assignment(): void
    {
        $foh = $this->staff('front_of_house');
        $this->staff('kitchen');

        $page = $this->actingAs($foh)->get("/admin/requests/{$this->reservation->public_id}")->assertOk();

        $this->assertStringContainsString($foh->name, $page->getContent());
        $this->assertSame(1, User::query()->whereHas('staffTeams', fn ($q) => $q->where('code', 'front_of_house'))->count());
    }
}
