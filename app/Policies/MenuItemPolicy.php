<?php

namespace App\Policies;

use App\Models\MenuItem;
use App\Models\User;
use App\Modules\Identity\CurrentActor;
use App\Modules\Menu\Support\MenuAuthorization;

/**
 * Menu CMS. Admin and editor manage dishes; staff can only change
 * availability. Reading published dishes is public (MenuRetriever).
 */
class MenuItemPolicy
{
    public function __construct(private readonly CurrentActor $actors) {}

    public function viewAny(User $actor): bool
    {
        return MenuAuthorization::canView($this->actors->for($actor));
    }

    public function create(User $actor): bool
    {
        return MenuAuthorization::canManage($this->actors->for($actor));
    }

    public function update(User $actor, MenuItem $item): bool
    {
        return MenuAuthorization::canManage($this->actors->for($actor));
    }

    public function publish(User $actor, MenuItem $item): bool
    {
        return MenuAuthorization::canManage($this->actors->for($actor));
    }

    public function verifyAllergens(User $actor, MenuItem $item): bool
    {
        return MenuAuthorization::canManage($this->actors->for($actor));
    }

    public function setAvailability(User $actor, MenuItem $item): bool
    {
        return MenuAuthorization::canSetAvailability($this->actors->for($actor));
    }

    public function manageCategories(User $actor): bool
    {
        return MenuAuthorization::canManage($this->actors->for($actor));
    }
}
