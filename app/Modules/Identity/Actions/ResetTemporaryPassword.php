<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Support\AdminGuard;
use App\Modules\Identity\Support\SessionRevoker;
use Illuminate\Support\Facades\DB;

/**
 * Sets a new temporary password chosen by the admin. The password is never
 * displayed, logged, or audited; the user must change it on next login.
 */
class ResetTemporaryPassword
{
    public function __construct(
        private readonly AdminGuard $guard,
        private readonly SessionRevoker $sessions,
        private readonly WriteAuditEvent $audit,
    ) {}

    public function handle(?User $actor, User $user, #[\SensitiveParameter] string $temporaryPassword): User
    {
        return DB::transaction(function () use ($actor, $user, $temporaryPassword) {
            $this->guard->assertActorIsAdmin($actor);

            /** @var User $user */
            $user = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            $user->forceFill([
                'password' => $temporaryPassword,
                'must_change_password' => true,
            ])->save();

            $this->sessions->revoke($user, 'password_reset');
            $this->audit->handle($actor, 'user.password_reset', $user);

            return $user;
        });
    }
}
