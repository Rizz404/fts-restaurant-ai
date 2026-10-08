<?php

namespace App\Policies;

use App\Models\RestaurantSetting;
use App\Models\User;
use App\Modules\Identity\CurrentActor;

/**
 * Restaurant settings are admin-only. No global before() bypass.
 */
class RestaurantSettingPolicy
{
    public function __construct(private readonly CurrentActor $actors) {}

    public function update(User $actor, RestaurantSetting $settings): bool
    {
        return $this->actors->for($actor)->isAdmin();
    }
}
