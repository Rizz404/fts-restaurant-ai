<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Enums\RoleCode;
use App\Modules\Identity\Support\AdminGuard;
use App\Modules\Identity\Support\SessionRevoker;
use App\Modules\Identity\Validation\UserAccessRules;
use Illuminate\Support\Facades\DB;

/**
 * Updates profile, active flag, roles, and staff team membership in one
 * transaction.
 */
class UpdateUserAccess
{
    public function __construct(
        private readonly AdminGuard $guard,
        private readonly SessionRevoker $sessions,
        private readonly WriteAuditEvent $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Validated with UserAccessRules::rules($user).
     */
    public function handle(User $actor, User $user, array $data): User
    {
        UserAccessRules::assertConsistent($data);

        return DB::transaction(function () use ($actor, $user, $data) {
            $this->guard->assertActorIsAdmin($actor);

            /** @var User $user */
            $user = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            $before = $this->snapshot($user);

            $staysActive = (bool) ($data['is_active'] ?? $user->is_active);
            $staysAdmin = in_array(RoleCode::Admin->value, (array) $data['roles'], true);

            if (! $staysActive || ! $staysAdmin) {
                $this->guard->assertAnotherActiveAdminRemains($user, $staysActive ? 'roles' : 'is_active');
            }

            $user->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'preferred_locale' => $data['preferred_locale'] ?? $user->preferred_locale,
                'is_active' => $staysActive,
            ])->save();

            ProvisionUser::syncAccess($user, $data);

            $after = $this->snapshot($user->refresh());
            $changes = $this->diff($before, $after);

            if ($before['is_active'] && ! $after['is_active']) {
                $this->sessions->revoke($user, 'deactivated');
            }

            if ($changes !== []) {
                $this->audit->handle($actor, 'user.access_updated', $user, ['changes' => $changes]);
            }

            return $user;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(User $user): array
    {
        $user->load(['roles:id,code', 'staffTeams:id,code']);

        return [
            'name' => $user->name,
            'email' => $user->email,
            'preferred_locale' => $user->preferred_locale,
            'is_active' => $user->is_active,
            'roles' => $user->roles->map(fn ($role) => $role->code->value)->sort()->values()->all(),
            'staff_teams' => $user->staffTeams->map(fn ($team) => $team->code->value)->sort()->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, array{from: mixed, to: mixed}>
     */
    private function diff(array $before, array $after): array
    {
        $changes = [];

        foreach ($after as $key => $value) {
            if ($before[$key] !== $value) {
                // Email changes are recorded without the addresses themselves.
                $changes[$key] = $key === 'email' ? ['from' => '[changed]', 'to' => '[changed]'] : ['from' => $before[$key], 'to' => $value];
            }
        }

        return $changes;
    }
}
