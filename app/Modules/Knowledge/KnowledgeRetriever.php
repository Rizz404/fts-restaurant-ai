<?php

namespace App\Modules\Knowledge;

use App\Models\KnowledgeArticle;
use App\Models\KnowledgeRevision;
use App\Modules\Knowledge\Data\KnowledgeEvidence;
use App\Modules\Knowledge\Enums\KnowledgeCategory;
use App\Modules\Knowledge\Queries\VisibleRevisions;
use App\Modules\Knowledge\Support\TextNormalizer;
use Illuminate\Support\Facades\DB;

/**
 * Deterministic retrieval over published info articles (PLAN_04).
 *
 * The scope (published status and effective window) is applied before
 * ranking. All locales are searched so cross-language aliases match; returned
 * text is always in the requested locale. Scoring per translation:
 * title phrase +30, keywords phrase +20, body phrase +10, and per distinct
 * matching token +3 title, +2 keywords, +1 body. Each revision takes its
 * best translation score; ties break on article_id ascending. Filtering and
 * scoring run in SQL over pre-normalized columns (portable to PostgreSQL and
 * SQLite), never by loading all content into PHP.
 */
final class KnowledgeRetriever
{
    public const MAX_QUERY_LENGTH = 500;

    public const MAX_RESULTS = 5;

    /**
     * @return list<KnowledgeEvidence>
     */
    public function search(string $locale, string $query, ?string $category = null, int $limit = self::MAX_RESULTS): array
    {
        $phrase = TextNormalizer::normalize(mb_substr($query, 0, self::MAX_QUERY_LENGTH));
        $tokens = TextNormalizer::tokens($phrase);
        $limit = max(1, min(self::MAX_RESULTS, $limit));

        if ($phrase === '' || $tokens === []) {
            return [];
        }

        [$scoreSql, $bindings] = $this->scoreExpression($phrase, $tokens);

        $visible = VisibleRevisions::query()
            ->when(
                $category !== null && in_array($category, KnowledgeCategory::values(), true),
                fn ($builder) => $builder->where('knowledge_revisions.category', $category),
            )
            ->select('knowledge_revisions.id');

        $rows = DB::table('knowledge_translations as t')
            ->join('knowledge_revisions as r', 'r.id', '=', 't.revision_id')
            ->whereIn('t.revision_id', $visible->toBase())
            ->groupBy('t.revision_id', 'r.article_id')
            ->selectRaw("t.revision_id, r.article_id, MAX({$scoreSql}) as score", $bindings)
            ->havingRaw("MAX({$scoreSql}) > 0", $bindings)
            ->orderByDesc('score')
            ->orderBy('r.article_id')
            ->limit($limit)
            ->get();

        return $this->evidence($rows->all(), $locale);
    }

    /**
     * Single published revision, by article id or slug.
     */
    public function find(int|string $article): ?KnowledgeRevision
    {
        return VisibleRevisions::query()
            ->whereHas('article', fn ($query) => is_int($article)
                ? $query->whereKey($article)
                : $query->where('slug', $article))
            ->with(['article', 'translations'])
            ->first();
    }

    /**
     * Evidence for a revision by ID, only if it is still published and in
     * its effective window (used to re-verify sources before saving a turn).
     */
    public function findVisibleRevision(int $revisionId): ?KnowledgeRevision
    {
        return VisibleRevisions::query()
            ->whereKey($revisionId)
            ->with(['article', 'translations'])
            ->first();
    }

    public static function toEvidence(KnowledgeRevision $revision, string $locale, int $score = 0): KnowledgeEvidence
    {
        $translation = $revision->translation($locale);
        /** @var KnowledgeArticle $article */
        $article = $revision->article;

        return new KnowledgeEvidence(
            articleId: $article->id,
            revisionId: $revision->id,
            version: $revision->version,
            locale: $translation?->locale ?? $locale,
            slug: $article->slug,
            category: $revision->category->value,
            title: $translation?->title ?? '',
            excerpt: KnowledgeEvidence::excerpt($translation?->body ?? ''),
            effectiveFrom: $revision->effective_from,
            effectiveUntil: $revision->effective_until,
            sourcePath: route('experience.info.show', $article->slug, false),
            score: $score,
        );
    }

    /**
     * @param  list<string>  $tokens
     * @return array{0: string, 1: list<string>}
     */
    private function scoreExpression(string $phrase, array $tokens): array
    {
        $parts = [];
        $bindings = [];
        $add = function (string $column, string $needle, int $points) use (&$parts, &$bindings) {
            $parts[] = "CASE WHEN {$column} LIKE ? ESCAPE '!' THEN {$points} ELSE 0 END";
            $bindings[] = '%'.$this->escapeLike($needle).'%';
        };

        $add('t.search_title', $phrase, 30);
        $add('t.search_keywords', $phrase, 20);
        $add('t.search_body', $phrase, 10);

        foreach ($tokens as $token) {
            $add('t.search_title', $token, 3);
            $add('t.search_keywords', $token, 2);
            $add('t.search_body', $token, 1);
        }

        return ['('.implode(' + ', $parts).')', $bindings];
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }

    /**
     * @param  list<object{revision_id: int, article_id: int, score: int|string}>  $rows
     * @return list<KnowledgeEvidence>
     */
    private function evidence(array $rows, string $locale): array
    {
        if ($rows === []) {
            return [];
        }

        $revisions = KnowledgeRevision::query()
            ->with(['article', 'translations'])
            ->whereIn('id', array_map(fn ($row) => $row->revision_id, $rows))
            ->get()
            ->keyBy('id');

        $results = [];
        foreach ($rows as $row) {
            $revision = $revisions->get($row->revision_id);
            if ($revision !== null) {
                $results[] = self::toEvidence($revision, $locale, (int) $row->score);
            }
        }

        return $results;
    }
}
