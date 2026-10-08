<?php

namespace App\Modules\Knowledge\Queries;

use App\Models\KnowledgeRevision;
use App\Modules\Knowledge\Enums\RevisionStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * The single read scope for published info: published status and inside the
 * effective window. Info is public, so there is no audience or actor
 * scope. Used by pages, API, and retrieval alike; no admin bypass.
 */
final class VisibleRevisions
{
    /**
     * @return Builder<KnowledgeRevision>
     */
    public static function query(?CarbonImmutable $now = null): Builder
    {
        $now ??= CarbonImmutable::now();

        return KnowledgeRevision::query()
            ->where('knowledge_revisions.status', RevisionStatus::Published->value)
            ->where('knowledge_revisions.effective_from', '<=', $now)
            ->where(fn (Builder $query) => $query
                ->whereNull('knowledge_revisions.effective_until')
                ->orWhere('knowledge_revisions.effective_until', '>', $now));
    }
}
