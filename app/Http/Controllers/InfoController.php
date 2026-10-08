<?php

namespace App\Http\Controllers;

use App\Modules\Experience\Enums\SceneCode;
use App\Modules\Knowledge\Data\KnowledgeEvidence;
use App\Modules\Knowledge\Enums\KnowledgeCategory;
use App\Modules\Knowledge\KnowledgeRetriever;
use App\Modules\Knowledge\Queries\VisibleRevisions;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Public Info slide: restaurant information articles, searchable without AI.
 * Invalid input is ignored on pages; unavailable articles are 404.
 */
class InfoController extends ExperienceController
{
    public function index(Request $request, KnowledgeRetriever $retriever): View
    {
        $query = trim(mb_substr((string) $request->query('q', ''), 0, KnowledgeRetriever::MAX_QUERY_LENGTH));
        $category = in_array($request->query('category'), KnowledgeCategory::values(), true) ? $request->query('category') : null;

        $results = null;
        $latest = null;

        if ($query !== '') {
            $results = $retriever->search(app()->getLocale(), $query, $category);
        } else {
            $latest = VisibleRevisions::query()
                ->when($category, fn ($builder) => $builder->where('knowledge_revisions.category', $category))
                ->with(['article', 'translations'])
                ->orderByDesc('knowledge_revisions.published_at')
                ->orderBy('knowledge_revisions.article_id')
                ->paginate(10)
                ->withQueryString();
        }

        return $this->scene(SceneCode::Info, 'experience.info', [
            'query' => $query,
            'category' => $category,
            'results' => $results,
            'latest' => $latest,
            'categoryOptions' => KnowledgeCategory::options(),
            'uiExcerpt' => KnowledgeEvidence::UI_EXCERPT,
        ]);
    }

    public function show(string $slug, KnowledgeRetriever $retriever): View
    {
        $revision = $retriever->find($slug);
        abort_if($revision === null, 404);

        return $this->scene(SceneCode::Info, 'experience.info-show', [
            'revision' => $revision,
            'translation' => $revision->translation(app()->getLocale()),
        ]);
    }
}
