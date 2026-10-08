<?php

namespace App\Modules\Knowledge\Actions;

use App\Models\KnowledgeArticle;
use App\Models\KnowledgeRevision;
use App\Models\User;
use App\Modules\Identity\Actions\WriteAuditEvent;
use App\Modules\Knowledge\Enums\RevisionStatus;
use App\Modules\Knowledge\Support\KnowledgeAuthorization;
use Illuminate\Support\Facades\DB;

/**
 * Withdraws the published revision from reading and retrieval. The record
 * is kept for history; a new draft can be published later.
 */
class ArchiveRevision
{
    public function __construct(private readonly WriteAuditEvent $audit) {}

    public function handle(User $actor, KnowledgeArticle $article): KnowledgeRevision
    {
        return DB::transaction(function () use ($actor, $article) {
            KnowledgeAuthorization::assertCanPublish($actor);

            /** @var KnowledgeArticle $article */
            $article = KnowledgeArticle::query()->whereKey($article->getKey())->lockForUpdate()->firstOrFail();

            /** @var KnowledgeRevision|null $published */
            $published = $article->published()->first();
            if ($published === null) {
                abort(409);
            }

            $published->forceFill(['status' => RevisionStatus::Archived])->save();
            $article->touch();

            $this->audit->handle($actor, 'info.archived', $article, ['version' => $published->version]);

            return $published;
        });
    }
}
