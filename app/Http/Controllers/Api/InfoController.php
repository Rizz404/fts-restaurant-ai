<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\InfoSearchRequest;
use App\Modules\Knowledge\Data\KnowledgeEvidence;
use App\Modules\Knowledge\KnowledgeRetriever;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Read-only public info API (/api/v1/info). Throttled per IP.
 */
class InfoController extends Controller
{
    public function __construct(private readonly KnowledgeRetriever $retriever) {}

    public function index(InfoSearchRequest $request): JsonResponse
    {
        $results = $this->retriever->search(
            $request->searchLocale(),
            $request->validated('q'),
            $request->validated('category'),
        );

        return ApiResponse::success(
            array_map(fn (KnowledgeEvidence $evidence) => $evidence->toArray(KnowledgeEvidence::UI_EXCERPT), $results),
            ['count' => count($results)],
        );
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $locale = $request->validate(['locale' => ['nullable', Rule::in(config('restaurant.locales'))]])['locale'] ?? app()->getLocale();

        $revision = $this->retriever->find($slug);
        abort_if($revision === null, 404);

        $translation = $revision->translation($locale);

        return ApiResponse::success(KnowledgeRetriever::toEvidence($revision, $locale)->toArray() + [
            'body' => $translation?->body ?? '',
            'tags' => $translation?->tags ?? [],
        ]);
    }
}
