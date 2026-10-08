<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\User;
use App\Modules\Identity\Actions\DisableUser;
use App\Modules\Identity\Actions\ResetTemporaryPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\BuildsRestaurantData;
use Tests\TestCase;

/**
 * Plan 2: staff sign-in, temporary passwords, throttling, the admin area,
 * last-admin protection, provisioning, preferences, and audit (no passwords).
 */
class IdentityAccessTest extends TestCase
{
    use BuildsRestaurantData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();
    }

    public function test_login_normalizes_email_and_stores_preferred_locale(): void
    {
        $user = $this->makeUser(['admin'], attributes: ['email' => 'mixed.case@example.test', 'preferred_locale' => 'ja']);

        $this->post('/login', ['email' => '  Mixed.Case@Example.TEST ', 'password' => 'password-for-tests'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame('ja', session('locale'));
    }

    public function test_wrong_password_and_inactive_account_cannot_sign_in(): void
    {
        $this->makeUser(attributes: ['email' => 'a@example.test']);
        $this->makeUser(attributes: ['email' => 'off@example.test', 'is_active' => false]);

        $this->post('/login', ['email' => 'a@example.test', 'password' => 'wrong-password'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => 'off@example.test', 'password' => 'password-for-tests'])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_is_throttled_after_five_attempts(): void
    {
        $this->makeUser(attributes: ['email' => 'throttle@example.test']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'throttle@example.test', 'password' => 'wrong-password']);
        }

        $this->post('/login', ['email' => 'throttle@example.test', 'password' => 'password-for-tests'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_temporary_password_must_be_changed_on_pages_and_json(): void
    {
        $user = $this->makeUser(['admin'], attributes: ['must_change_password' => true]);

        $this->actingAs($user)->get('/admin')->assertRedirect(route('password.change'));
        $this->actingAs($user)->getJson('/api/v1/notifications')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'password_change_required');

        $this->actingAs($user)->post('/password/change', [
            'current_password' => 'password-for-tests',
            'password' => 'a-new-long-password',
            'password_confirmation' => 'a-new-long-password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertFalse($user->refresh()->must_change_password);
        $this->assertTrue(Hash::check('a-new-long-password', $user->password));
        $this->actingAs($user)->get('/admin')->assertOk();
    }

    public function test_admin_area_requires_a_staff_role(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
        $this->actingAs($this->makeUser([]))->get('/admin')->assertForbidden();
        $this->actingAs($this->admin())->get('/admin')->assertOk();
        $this->actingAs($this->editor())->get('/admin')->assertOk();
        $this->actingAs($this->staff('kitchen'))->get('/admin')->assertOk();
    }

    public function test_roles_do_not_leak_between_areas(): void
    {
        $editor = $this->editor();
        $staff = $this->staff();

        $this->actingAs($editor)->get('/admin/users')->assertForbidden();
        $this->actingAs($editor)->get('/admin/operations')->assertForbidden();
        $this->actingAs($staff)->get('/admin/users')->assertForbidden();
        $this->actingAs($staff)->get('/admin/settings')->assertForbidden();
        $this->actingAs($staff)->get('/admin/menu/create')->assertForbidden();
    }

    public function test_staff_with_two_teams_and_an_editor_role_gets_no_global_permission(): void
    {
        $user = $this->makeUser(['staff', 'editor'], ['front_of_house', 'kitchen']);

        $this->actingAs($user)->get('/admin')->assertOk();
        $this->actingAs($user)->get('/admin/requests')->assertOk();
        $this->actingAs($user)->get('/admin/users')->assertForbidden();
        $this->actingAs($user)->get('/admin/settings')->assertForbidden();
        $this->actingAs($user)->get('/admin/operations')->assertForbidden();
    }

    public function test_admin_and_staff_without_a_team_see_no_requests(): void
    {
        [, $guest] = $this->makeGuest();
        $request = $this->feedbackFor($guest);

        foreach ([$this->admin(), $this->makeUser(['staff'])] as $user) {
            $this->actingAs($user)->get('/admin/requests')->assertForbidden();
            $this->actingAs($user)->get("/admin/requests/{$request->public_id}")->assertForbidden();
            $this->actingAs($user)->getJson("/api/v1/staff/requests/{$request->public_id}/messages")->assertForbidden();
        }
    }

    public function test_admin_provisions_user_with_temporary_password_and_audit_without_password(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'New Person',
            'email' => 'New.Person@Example.test',
            'preferred_locale' => 'en',
            'roles' => ['staff'],
            'staff_teams' => ['kitchen'],
            'password' => 'temporary-pass-123',
            'password_confirmation' => 'temporary-pass-123',
        ])->assertRedirect(route('admin.users.index'));

        $user = User::query()->where('email', 'new.person@example.test')->firstOrFail();
        $this->assertTrue($user->must_change_password);
        $this->assertEqualsCanonicalizing(['kitchen'], $user->staffTeams()->get()->map(fn ($team) => $team->code->value)->all());

        $audit = AuditEvent::query()->where('action', 'user.provisioned')->firstOrFail();
        $this->assertStringNotContainsString('temporary-pass-123', json_encode($audit->metadata));
    }

    public function test_invalid_assignments_are_rejected(): void
    {
        $this->actingAs($this->admin())->post('/admin/users', [
            'name' => 'X',
            'email' => 'x@example.test',
            'preferred_locale' => 'xx',
            'roles' => ['superuser'],
            'staff_teams' => ['finance'],
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors(['preferred_locale', 'roles.0', 'staff_teams.0', 'password']);
    }

    public function test_teams_can_only_be_given_together_with_the_staff_role(): void
    {
        $admin = $this->admin();
        $base = ['name' => 'Y', 'email' => 'y@example.test', 'preferred_locale' => 'id', 'password' => 'temporary-pass-123', 'password_confirmation' => 'temporary-pass-123'];

        $this->actingAs($admin)->post('/admin/users', $base + ['roles' => ['editor'], 'staff_teams' => ['kitchen']])
            ->assertSessionHasErrors('staff_teams');

        $this->assertDatabaseMissing('users', ['email' => 'y@example.test']);

        // A staff member without a team is allowed; they simply handle no requests.
        $this->actingAs($admin)->post('/admin/users', $base + ['roles' => ['staff'], 'staff_teams' => []])
            ->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', ['email' => 'y@example.test']);
    }

    public function test_last_active_admin_cannot_lose_the_admin_role(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put("/admin/users/{$admin->id}", [
            'name' => $admin->name,
            'email' => $admin->email,
            'preferred_locale' => 'id',
            'roles' => ['editor'],
            'staff_teams' => [],
            'is_active' => '1',
        ])->assertSessionHasErrors('roles');

        $this->assertTrue($admin->refresh()->roles()->where('code', 'admin')->exists());
    }

    public function test_disable_and_reset_revoke_access(): void
    {
        $admin = $this->admin();
        $this->admin();
        $user = $this->staff();

        app(ResetTemporaryPassword::class)->handle($admin, $user, 'brand-new-temp-pass');
        $this->assertTrue($user->refresh()->must_change_password);

        app(DisableUser::class)->handle($admin, $user);
        $this->assertFalse($user->refresh()->is_active);

        $this->actingAs($user)->get('/admin')->assertRedirect(route('login'));
    }

    public function test_preferences_api_validates_and_saves_locale_for_staff(): void
    {
        $user = $this->staff();

        $this->actingAs($user)->postJson('/api/v1/preferences', ['locale' => 'fr'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $this->actingAs($user)->postJson('/api/v1/preferences', ['locale' => 'ja'])->assertOk();

        $this->assertSame('ja', $user->refresh()->preferred_locale);
        $this->assertSame('ja', session('locale'));
    }

    public function test_logout_invalidates_session(): void
    {
        $this->actingAs($this->staff())->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_demo_accounts_command_refuses_production(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('restaurant:demo-accounts')->assertFailed();
    }

    public function test_demo_accounts_command_creates_the_four_demo_roles(): void
    {
        $this->artisan('restaurant:demo-accounts')->assertSuccessful();

        $this->assertSame(4, User::query()->where('email', 'like', 'demo.%@example.test')->count());
        $this->assertTrue(User::query()->where('email', 'demo.kitchen@example.test')->firstOrFail()->staffTeams()->get()->contains(fn ($team) => $team->code->value === 'kitchen'));
    }

    public function test_create_admin_command_prompts_for_secret_password(): void
    {
        $this->artisan('restaurant:create-admin', ['--email' => 'Boss@Example.test', '--name' => 'Boss'])
            ->expectsQuestion('Temporary password (min 12 characters)', 'admin-password-123')
            ->expectsQuestion('Confirm temporary password', 'admin-password-123')
            ->assertSuccessful();

        $boss = User::query()->where('email', 'boss@example.test')->firstOrFail();
        $this->assertTrue($boss->roles()->where('code', 'admin')->exists());
        $this->assertTrue($boss->must_change_password);
    }
}
