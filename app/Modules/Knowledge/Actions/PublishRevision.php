<?php

namespace App\Modules\Knowledge\Actions;

use App\Models\KnowledgeArticle;
use App\Models\KnowledgeRevision;
use App\Models\User;
use App\Modules\Identity\Actions\WriteAuditEvent;
use App\Modules\Knowledge\Enums\RevisionStatus;
use App\Modules\Knowledge\Support\KnowledgeAuthorization;
use App\Modules\Knowledge\Validation\PublicationRules;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Publishes the draft immediately and archives the previous published
 * revision in the same transaction (article row locked).
 */
class PublishRevision
{
    public function __construct(private readonly WriteAuditEvent $audit) {}

    public function handle(User $actor, KnowledgeArticle $article): KnowledgeRevision
    {
        return DB::transaction(function () use ($actor, $article) {
            KnowledgeAuthorization::assertCanPublish($actor);

            /** @var KnowledgeArticle $article */
            $article = KnowledgeArticle::query()->whereKey($article->getKey())->lockForUpdate()->firstOrFail();

            /** @var KnowledgeRevision|null $draft */
            $draft = $article->draft()->first();
            if ($draft === null) {
                abort(409);
            }

            $now = CarbonImmutable::now();
            $errors = PublicationRules::errors($draft, $now);
            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            $previous = $article->published()->first();
            $previous?->forceFill(['status' => RevisionStatus::Archived])->save();

            $draft->forceFill([
                'status' => RevisionStatus::Published,
                'effective_from' => $draft->effective_from ?? $now,
                'published_by' => $actor->id,
                'published_at' => $now,
            ])->save();
            $article->touch();

            $this->audit->handle($actor, 'info.published', $article, [
                'version' => $draft->version,
                'archived_version' => $previous?->version,
            ]);

            return $draft;
        });
    }
}
