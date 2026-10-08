<?php

namespace App\Modules\Menu\Support;

use App\Models\User;
use App\Modules\Identity\ActorContext;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Menu CMS rules, used by MenuItemPolicy and re-checked inside action
 * transactions with a fresh ActorContext.
 *
 * - Admin and editor: create, edit, publish, archive, verify allergens,
 *   manage categories.
 * - Staff (any team): only mark a dish available or sold out.
 */
final class MenuAuthorization
{
    public static function canManage(ActorContext $actor): bool
    {
        return $actor->isAdmin() || $actor->isEditor();
    }

    public static function canSetAvailability(ActorContext $actor): bool
    {
        return self::canManage($actor) || $actor->isStaff();
    }

    public static function canView(ActorContext $actor): bool
    {
        return $actor->canAccessAdminArea();
    }

    /**
     * @throws AuthorizationException
     */
    public static function assertCanManage(User $user): void
    {
        if (! self::canManage(ActorContext::load($user))) {
            throw new AuthorizationException;
        }
    }

    /**
     * @throws AuthorizationException
     */
    public static function assertCanSetAvailability(User $user): void
    {
        if (! self::canSetAvailability(ActorContext::load($user))) {
            throw new AuthorizationException;
        }
    }
}
