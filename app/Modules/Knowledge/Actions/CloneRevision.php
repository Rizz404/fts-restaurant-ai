<?php

namespace App\Modules\Knowledge\Actions;

use App\Models\KnowledgeArticle;
use App\Models\KnowledgeRevision;
use App\Models\KnowledgeTranslation;
use App\Models\User;
use App\Modules\Identity\Actions\WriteAuditEvent;
use App\Modules\Knowledge\Enums\RevisionStatus;
use App\Modules\Knowledge\Support\KnowledgeAuthorization;
use Illuminate\Support\Facades\DB;

/**
 * Starts the next info draft from the latest revision (published or archived).
 * If a draft already exists it is returned unchanged (single draft rule).
 */
class CloneRevision
{
    public function __construct(private readonly WriteAuditEvent $audit) {}

    public function handle(User $actor, KnowledgeArticle $article): KnowledgeRevision
    {
        return DB::transaction(function () use ($actor, $article) {
            /** @var KnowledgeArticle $article */
            $article = KnowledgeArticle::query()->whereKey($article->getKey())->lockForUpdate()->firstOrFail();
            KnowledgeAuthorization::assertCanEdit($actor);

            if ($existing = $article->draft()->first()) {
                return $existing;
            }

            /** @var KnowledgeRevision $source */
            $source = $article->published()->first()
                ?? $article->revisions()->orderByDesc('version')->firstOrFail();
            $source->load('translations');

            $draft = KnowledgeRevision::create([
                'article_id' => $article->id,
                'version' => (int) $article->revisions()->max('version') + 1,
                'status' => RevisionStatus::Draft,
                'category' => $source->category,
                'effective_from' => null,
                'effective_until' => $source->effective_until,
                'created_by' => $actor->id,
            ]);

            foreach ($source->translations as $translation) {
                KnowledgeTranslation::create([
                    'revision_id' => $draft->id,
                    'locale' => $translation->locale,
                    'title' => $translation->title,
                    'body' => $translation->body,
                    'tags' => $translation->tags,
                    'aliases' => $translation->aliases,
                ]);
            }

            $this->audit->handle($actor, 'info.draft_created', $article, [
                'from_version' => $source->version,
                'version' => $draft->version,
            ]);

            return $draft;
        });
    }
}
