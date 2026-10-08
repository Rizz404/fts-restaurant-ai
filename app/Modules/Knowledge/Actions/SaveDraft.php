<?php

namespace App\Modules\Knowledge\Actions;

use App\Models\KnowledgeArticle;
use App\Models\KnowledgeRevision;
use App\Models\User;
use App\Modules\Knowledge\Support\DraftWriter;
use App\Modules\Knowledge\Support\KnowledgeAuthorization;
use Illuminate\Support\Facades\DB;

/**
 * Updates the article's single draft. Without a draft the caller must first
 * create one with CloneRevision.
 */
class SaveDraft
{
    /**
     * @param  array<string, mixed>  $payload  From ArticleRules::payload().
     */
    public function handle(User $actor, KnowledgeArticle $article, array $payload): KnowledgeRevision
    {
        return DB::transaction(function () use ($actor, $article, $payload) {
            /** @var KnowledgeArticle $article */
            $article = KnowledgeArticle::query()->whereKey($article->getKey())->lockForUpdate()->firstOrFail();
            KnowledgeAuthorization::assertCanEdit($actor);

            $draft = $article->draft()->first();
            if ($draft === null) {
                abort(409);
            }

            if (isset($payload['slug']) && $payload['slug'] !== $article->slug && ! $article->slugIsLocked()) {
                $article->forceFill(['slug' => $payload['slug']])->save();
            }

            DraftWriter::write($draft, $payload);
            $article->touch();

            return $draft;
        });
    }
}
