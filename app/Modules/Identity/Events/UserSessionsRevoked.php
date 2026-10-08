<?php

namespace App\Modules\Identity\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched after a user's sessions are revoked (deactivation or password
 * reset). Plan 5 listens to cancel the user's active AI lease and turn.
 */
class UserSessionsRevoked
{
    use Dispatchable;

    public function __construct(
        public readonly int $userId,
        public readonly string $reason,
    ) {}
}
