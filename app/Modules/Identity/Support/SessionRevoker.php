<?php

namespace App\Modules\Identity\Support;

use App\Models\User;
use App\Modules\Identity\Events\UserSessionsRevoked;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ends every existing session of a user.
 *
 * Deletes stored database sessions and rotates the remember token. Other
 * session drivers are still covered: EnsureAccountIsActive logs out inactive
 * users and AuthenticateSession rejects sessions after a password change.
 */
final class SessionRevoker
{
    public function revoke(User $user, string $reason): void
    {
        $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->getKey())->delete();
        }

        DB::afterCommit(fn () => UserSessionsRevoked::dispatch($user->getKey(), $reason));
    }
}
