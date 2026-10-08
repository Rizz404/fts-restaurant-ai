<?php

namespace App\Modules\Knowledge\Support;

use App\Models\User;
use App\Modules\Identity\ActorContext;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * CMS management rules for info articles, used by KnowledgeArticlePolicy and
 * re-checked inside action transactions with a fresh ActorContext.
 * Admin and editor create and edit drafts; only admin publishes and archives.
 * Reading published articles is public (see VisibleRevisions).
 */
final class KnowledgeAuthorization
{
    public static function canManageCms(ActorContext $actor): bool
    {
        return $actor->isAdmin() || $actor->isEditor();
    }

    public static function canEdit(ActorContext $actor): bool
    {
        return self::canManageCms($actor);
    }

    public static function canPublish(ActorContext $actor): bool
    {
        return $actor->isAdmin();
    }

    /**
     * @throws AuthorizationException
     */
    public static function assertCanEdit(User $user): void
    {
        if (! self::canEdit(ActorContext::load($user))) {
            throw new AuthorizationException;
        }
    }

    /**
     * @throws AuthorizationException
     */
    public static function assertCanPublish(User $user): void
    {
        if (! self::canPublish(ActorContext::load($user))) {
            throw new AuthorizationException;
        }
    }
}
