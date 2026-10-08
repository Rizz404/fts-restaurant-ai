<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Identity\CurrentActor;

/**
 * Account management is admin-only. No global before() bypass.
 */
class UserPolicy
{
    public function __construct(private readonly CurrentActor $actors) {}

    public function viewAny(User $actor): bool
    {
        return $this->actors->for($actor)->isAdmin();
    }

    public function create(User $actor): bool
    {
        return $this->actors->for($actor)->isAdmin();
    }

    public function update(User $actor, User $user): bool
    {
        return $this->actors->for($actor)->isAdmin();
    }

    public function resetPassword(User $actor, User $user): bool
    {
        return $this->actors->for($actor)->isAdmin();
    }
}
