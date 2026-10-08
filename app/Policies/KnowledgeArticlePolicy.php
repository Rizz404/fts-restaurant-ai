<?php

namespace App\Policies;

use App\Models\KnowledgeArticle;
use App\Models\User;
use App\Modules\Identity\CurrentActor;
use App\Modules\Knowledge\Support\KnowledgeAuthorization;

/**
 * Info CMS management. Admin and editor create and edit drafts; only admin
 * publishes and archives. Reading published articles is public and is
 * governed by VisibleRevisions, not this policy.
 */
class KnowledgeArticlePolicy
{
    public function __construct(private readonly CurrentActor $actors) {}

    public function viewAny(User $actor): bool
    {
        return KnowledgeAuthorization::canManageCms($this->actors->for($actor));
    }

    public function create(User $actor): bool
    {
        return KnowledgeAuthorization::canManageCms($this->actors->for($actor));
    }

    public function update(User $actor, KnowledgeArticle $article): bool
    {
        return KnowledgeAuthorization::canEdit($this->actors->for($actor));
    }

    public function publish(User $actor, KnowledgeArticle $article): bool
    {
        return KnowledgeAuthorization::canPublish($this->actors->for($actor));
    }
}
