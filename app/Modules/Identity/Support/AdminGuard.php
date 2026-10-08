<?php

namespace App\Modules\Identity\Support;

use App\Models\User;
use App\Modules\Identity\ActorContext;
use App\Modules\Identity\Enums\RoleCode;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Transaction-time checks for admin operations. Call inside DB::transaction.
 */
final class AdminGuard
{
    /**
     * Re-verifies at commit time that the actor is still an active admin.
     *
     * @throws AuthorizationException
     */
    public function assertActorIsAdmin(?User $actor): void
    {
        if ($actor === null) {
            return; // Trusted system path (CLI command).
        }

        User::query()->whereKey($actor->getKey())->lockForUpdate()->first();

        if (! ActorContext::load($actor)->isAdmin()) {
            throw new AuthorizationException;
        }
    }

    /**
     * Blocks the change when $user is the only active admin. Locks every
     * active admin row first so two concurrent demotions cannot both pass.
     *
     * @throws ValidationException
     */
    public function assertAnotherActiveAdminRemains(User $user, string $field = 'roles'): void
    {
        $adminIds = User::query()
            ->active()
            ->withRole(RoleCode::Admin)
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('id');

        if ($adminIds->contains($user->getKey()) && $adminIds->count() <= 1) {
            throw ValidationException::withMessages([$field => __('ui.users.errors.last_admin')]);
        }
    }
}
