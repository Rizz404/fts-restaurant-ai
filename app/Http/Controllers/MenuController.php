<?php

namespace App\Http\Controllers;

use App\Models\MenuCategory;
use App\Modules\Experience\Enums\SceneCode;
use App\Modules\Experience\Queries\BuildScene;
use App\Modules\Menu\Data\MenuFilters;
use App\Modules\Menu\MenuRetriever;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Public Menu and Chef's Picks slides. Browsing, searching, and filtering
 * work without AI. Invalid filter input is ignored on pages; draft and
 * archived dishes are 404.
 */
class MenuController extends ExperienceController
{
    public function __construct(BuildScene $buildScene, private readonly MenuRetriever $menu)
    {
        parent::__construct($buildScene);
    }

    public function index(Request $request): View
    {
        $filters = MenuFilters::fromArray([
            'query' => $request->query('q'),
            'category' => $request->query('category'),
            'dietary_tags' => $request->query('diet'),
            'exclude_allergens' => $request->query('exclude_allergens'),
            'max_price' => $request->query('max_price'),
        ]);

        $result = $this->menu->catalog(app()->getLocale(), $filters);

        return $this->scene(SceneCode::Menu, 'experience.menu', [
            'filters' => $filters,
            'items' => $result->items,
            'unverifiedExcluded' => $result->unverifiedExcludedCount,
            'categories' => MenuCategory::query()->active()->with('translations')->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function show(string $slug): View
    {
        $item = $this->menu->findEvidence($slug, app()->getLocale());
        abort_if($item === null, 404);

        return $this->scene(SceneCode::Menu, 'experience.menu-show', ['item' => $item], ['menu_item_id' => $item->itemId]);
    }

    public function picks(): View
    {
        return $this->scene(SceneCode::Picks, 'experience.picks', [
            'items' => $this->menu->chefPicks(app()->getLocale()),
        ]);
    }
}
