<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\InfoArticleRequest;
use App\Models\KnowledgeArticle;
use App\Modules\Knowledge\Actions\ArchiveRevision;
use App\Modules\Knowledge\Actions\CloneRevision;
use App\Modules\Knowledge\Actions\CreateArticle;
use App\Modules\Knowledge\Actions\PublishRevision;
use App\Modules\Knowledge\Actions\SaveDraft;
use App\Modules\Knowledge\Enums\KnowledgeCategory;
use App\Modules\Knowledge\Validation\PublicationRules;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Info CMS: plain-text articles (hours, location, policy, FAQ) in id/en/ja
 * with draft → published → archived revisions. Editors write drafts; only
 * admins publish and archive.
 */
class InfoController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', KnowledgeArticle::class);

        $articles = KnowledgeArticle::query()
            ->with(['latestRevision.translations', 'draft:id,article_id,version', 'published:id,article_id,version'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(20);

        return view('admin.info.index', ['articles' => $articles]);
    }

    public function create(): View
    {
        Gate::authorize('create', KnowledgeArticle::class);

        return view('admin.info.create', $this->formOptions());
    }

    public function store(InfoArticleRequest $request, CreateArticle $create): RedirectResponse
    {
        $article = $create->handle($request->user(), $request->payload());

        return redirect()->route('admin.info.edit', $article)->with('status', __('info.created'));
    }

    public function edit(KnowledgeArticle $article): View
    {
        Gate::authorize('update', $article);

        $article->load(['draft.translations', 'published.translations', 'published.publisher:id,name']);

        return view('admin.info.edit', [
            'article' => $article,
            'draft' => $article->draft,
            'published' => $article->published,
            'slugLocked' => $article->slugIsLocked(),
            'completeness' => PublicationRules::completeness($article->draft),
            'canPublish' => Gate::allows('publish', $article),
        ] + $this->formOptions());
    }

    public function update(InfoArticleRequest $request, KnowledgeArticle $article, SaveDraft $save): RedirectResponse
    {
        $save->handle($request->user(), $article, $request->payload());

        return redirect()->route('admin.info.edit', $article)->with('status', __('info.draft_saved'));
    }

    public function revisions(KnowledgeArticle $article): View
    {
        Gate::authorize('update', $article);

        return view('admin.info.revisions', [
            'article' => $article,
            'revisions' => $article->revisions()->with(['translations', 'creator:id,name', 'publisher:id,name'])->orderByDesc('version')->get(),
        ]);
    }

    public function createDraft(Request $request, KnowledgeArticle $article, CloneRevision $clone): RedirectResponse
    {
        Gate::authorize('update', $article);
        $clone->handle($request->user(), $article);

        return redirect()->route('admin.info.edit', $article)->with('status', __('info.draft_created'));
    }

    public function publish(Request $request, KnowledgeArticle $article, PublishRevision $publish): RedirectResponse
    {
        Gate::authorize('publish', $article);
        $publish->handle($request->user(), $article);

        return redirect()->route('admin.info.edit', $article)->with('status', __('info.published'));
    }

    public function archive(Request $request, KnowledgeArticle $article, ArchiveRevision $archive): RedirectResponse
    {
        Gate::authorize('publish', $article);
        $archive->handle($request->user(), $article);

        return redirect()->route('admin.info.edit', $article)->with('status', __('info.archived'));
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return ['categoryOptions' => KnowledgeCategory::options()];
    }
}
