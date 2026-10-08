<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MenuSearchRequest;
use App\Modules\Menu\Data\MenuEvidence;
use App\Modules\Menu\MenuRetriever;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Read-only public menu API (/api/v1/menu). Throttled per IP. Dishes with
 * unverified allergen data never carry allergen or diet lists.
 */
class MenuController extends Controller
{
    public function __construct(private readonly MenuRetriever $menu) {}

    public function index(MenuSearchRequest $request): JsonResponse
    {
        $result = $this->menu->search($request->searchLocale(), $request->filters(), $request->limit());

        return ApiResponse::success(
            array_map(fn (MenuEvidence $item) => $item->toArray(300), $result->items),
            ['count' => count($result->items), 'unverified_excluded_count' => $result->unverifiedExcludedCount],
        );
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $locale = $request->validate(['locale' => ['nullable', Rule::in(config('restaurant.locales'))]])['locale'] ?? app()->getLocale();

        $item = $this->menu->findEvidence($slug, $locale);
        abort_if($item === null, 404);

        return ApiResponse::success($item->toArray());
    }
}
