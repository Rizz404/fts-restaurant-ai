<?php

namespace App\Modules\Identity\Actions;

use App\Models\Role;
use App\Models\StaffTeam;
use App\Models\User;
use App\Modules\Identity\Support\AdminGuard;
use App\Modules\Identity\Validation\UserAccessRules;
use Illuminate\Support\Facades\DB;

/**
 * Creates a staff account with a temporary password.
 *
 * $actor is the admin doing it, or null for trusted CLI provisioning.
 */
class ProvisionUser
{
    public function __construct(
        private readonly AdminGuard $guard,
        private readonly WriteAuditEvent $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Validated with UserAccessRules::rules().
     */
    public function handle(?User $actor, array $data): User
    {
        UserAccessRules::assertConsistent($data);

        return DB::transaction(function () use ($actor, $data) {
            $this->guard->assertActorIsAdmin($actor);

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'preferred_locale' => $data['preferred_locale'] ?? config('restaurant.default_locale'),
                'is_active' => true,
                'must_change_password' => true,
            ]);

            $access = self::syncAccess($user, $data);

            $this->audit->handle($actor, 'user.provisioned', $user, $access);

            return $user;
        });
    }

    /**
     * Syncs roles and staff teams.
     *
     * @param  array<string, mixed>  $data
     * @return array{roles: list<string>, staff_teams: list<string>}
     */
    public static function syncAccess(User $user, array $data): array
    {
        $roles = array_values(array_unique((array) ($data['roles'] ?? [])));
        $teams = array_values(array_unique((array) ($data['staff_teams'] ?? [])));

        sort($roles);
        sort($teams);

        $user->roles()->sync(Role::query()->whereIn('code', $roles)->pluck('id'));
        $user->staffTeams()->sync(StaffTeam::query()->whereIn('code', $teams)->pluck('id'));

        return ['roles' => $roles, 'staff_teams' => $teams];
    }
}
