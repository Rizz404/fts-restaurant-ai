<?php

namespace App\Modules\Knowledge\Actions;

use App\Models\KnowledgeArticle;
use App\Models\KnowledgeRevision;
use App\Models\User;
use App\Modules\Identity\Actions\WriteAuditEvent;
use App\Modules\Knowledge\Enums\RevisionStatus;
use App\Modules\Knowledge\Support\DraftWriter;
use App\Modules\Knowledge\Support\KnowledgeAuthorization;
use Illuminate\Support\Facades\DB;

/**
 * Creates an info article with its first draft (version 1).
 */
class CreateArticle
{
    public function __construct(private readonly WriteAuditEvent $audit) {}

    /**
     * @param  array<string, mixed>  $payload  From ArticleRules::payload().
     */
    public function handle(User $actor, array $payload): KnowledgeArticle
    {
        return DB::transaction(function () use ($actor, $payload) {
            KnowledgeAuthorization::assertCanEdit($actor);

            $article = KnowledgeArticle::create([
                'slug' => $payload['slug'],
                'created_by' => $actor->id,
            ]);

            $draft = KnowledgeRevision::create([
                'article_id' => $article->id,
                'version' => 1,
                'status' => RevisionStatus::Draft,
                'category' => $payload['category'],
                'created_by' => $actor->id,
            ]);

            DraftWriter::write($draft, $payload);

            $this->audit->handle($actor, 'info.article_created', $article, ['slug' => $article->slug]);

            return $article;
        });
    }
}
