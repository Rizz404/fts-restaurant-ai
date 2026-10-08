<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The user replaces their (temporary) password. The current password is
 * verified by ChangePasswordRequest before this runs.
 */
class ChangePassword
{
    public function __construct(
        private readonly WriteAuditEvent $audit,
    ) {}

    public function handle(User $user, #[\SensitiveParameter] string $newPassword): User
    {
        return DB::transaction(function () use ($user, $newPassword) {
            $user->forceFill([
                'password' => $newPassword,
                'must_change_password' => false,
            ])->save();

            $this->audit->handle($user, 'user.password_changed', $user);

            return $user;
        });
    }
}
