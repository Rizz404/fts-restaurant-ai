<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Support\AdminGuard;
use App\Modules\Identity\Support\SessionRevoker;
use Illuminate\Support\Facades\DB;

/**
 * Deactivates an account (users are never hard-deleted) and ends its sessions.
 */
class DisableUser
{
    public function __construct(
        private readonly AdminGuard $guard,
        private readonly SessionRevoker $sessions,
        private readonly WriteAuditEvent $audit,
    ) {}

    public function handle(?User $actor, User $user): User
    {
        return DB::transaction(function () use ($actor, $user) {
            $this->guard->assertActorIsAdmin($actor);

            /** @var User $user */
            $user = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            if (! $user->is_active) {
                return $user;
            }

            $this->guard->assertAnotherActiveAdminRemains($user, 'is_active');

            $user->forceFill(['is_active' => false])->save();
            $this->sessions->revoke($user, 'deactivated');
            $this->audit->handle($actor, 'user.disabled', $user);

            return $user;
        });
    }
}
