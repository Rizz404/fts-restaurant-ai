<?php

namespace App\Modules\Identity;

use App\Models\User;

/**
 * Per-request cache of ActorContext snapshots (bound as a scoped service).
 *
 * Use for reads such as menus and policies. Write actions re-load the
 * actor inside their transaction instead of trusting this snapshot.
 */
final class CurrentActor
{
    /** @var array<int, ActorContext> */
    private array $snapshots = [];

    public function for(User $user): ActorContext
    {
        return $this->snapshots[$user->getKey()] ??= ActorContext::load($user);
    }

    public function forget(User|int $user): void
    {
        unset($this->snapshots[$user instanceof User ? $user->getKey() : $user]);
    }
}
