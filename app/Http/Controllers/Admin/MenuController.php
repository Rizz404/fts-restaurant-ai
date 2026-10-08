<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AvailabilityRequest;
use App\Http\Requests\Admin\MenuItemRequest;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Modules\Menu\Actions\ArchiveMenuItem;
use App\Modules\Menu\Actions\CreateMenuItem;
use App\Modules\Menu\Actions\PublishMenuItem;
use App\Modules\Menu\Actions\SetAvailability;
use App\Modules\Menu\Actions\UpdateMenuItem;
use App\Modules\Menu\Actions\VerifyAllergens;
use App\Modules\Menu\Enums\AllergenRelation;
use App\Modules\Menu\Enums\MenuStatus;
use App\Modules\Menu\Validation\MenuItemRules;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Menu CMS. Admin and editor manage dishes; staff can reach the list only to
 * mark dishes available or sold out.
 */
class MenuController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', MenuItem::class);

        $search = trim((string) $request->query('q', ''));
        $status = MenuStatus::tryFrom((string) $request->query('status'));
        $categoryId = $request->integer('category') ?: null;

        $items = MenuItem::query()
            ->with(['translations', 'category.translations'])
            ->when($status, fn (Builder $query) => $query->where('status', $status->value))
            ->when($categoryId, fn (Builder $query) => $query->where('category_id', $categoryId))
            ->when($search !== '', function (Builder $query) use ($search) {
                $like = '%'.addcslashes(mb_strtolower($search), '%_\\').'%';
                $query->where(fn (Builder $query) => $query
                    ->whereRaw("lower(slug) like ? escape '\\'", [$like])
                    ->orWhereHas('translations', fn (Builder $translations) => $translations->whereRaw("lower(search_name) like ? escape '\\'", [$like])));
            })
            ->orderBy('category_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.menu.index', [
            'items' => $items,
            'filters' => ['q' => $search, 'status' => $status?->value, 'category' => $categoryId],
            'statusOptions' => collect(MenuStatus::cases())->mapWithKeys(fn (MenuStatus $s) => [$s->value => $s->label()])->all(),
            'categoryOptions' => $this->categoryOptions(),
            'canManage' => Gate::allows('create', MenuItem::class),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', MenuItem::class);

        return view('admin.menu.create', $this->formOptions());
    }

    public function store(MenuItemRequest $request, CreateMenuItem $create): RedirectResponse
    {
        $item = $create->handle($request->user(), $request->payload(), $request->file('photo'));

        return redirect()->route('admin.menu.edit', $item)->with('status', __('menu.created'));
    }

    public function edit(MenuItem $item): View
    {
        Gate::authorize('update', $item);

        $item->load(['translations', 'allergens', 'dietaryTags', 'category.translations', 'verifier:id,name']);

        return view('admin.menu.edit', [
            'item' => $item,
            'completeness' => MenuItemRules::completeness($item),
            'publicationErrors' => $item->isPublished() ? [] : MenuItemRules::publicationErrors($item),
            'slugLocked' => $item->slugIsLocked(),
        ] + $this->formOptions());
    }

    public function update(MenuItemRequest $request, MenuItem $item, UpdateMenuItem $update): RedirectResponse
    {
        $update->handle($request->user(), $item, $request->payload(), $request->file('photo'), $request->boolean('remove_photo'));

        return redirect()->route('admin.menu.edit', $item)->with('status', __('menu.updated'));
    }

    public function publish(Request $request, MenuItem $item, PublishMenuItem $publish): RedirectResponse
    {
        Gate::authorize('publish', $item);
        $publish->handle($request->user(), $item);

        return redirect()->route('admin.menu.edit', $item)->with('status', __('menu.published'));
    }

    public function archive(Request $request, MenuItem $item, ArchiveMenuItem $archive): RedirectResponse
    {
        Gate::authorize('publish', $item);
        $archive->handle($request->user(), $item);

        return redirect()->route('admin.menu.edit', $item)->with('status', __('menu.archived'));
    }

    public function availability(AvailabilityRequest $request, MenuItem $item, SetAvailability $setAvailability): RedirectResponse
    {
        $setAvailability->handle($request->user(), $item, $request->available());

        return redirect()->back()->with('status', __('menu.availability_updated'));
    }

    public function verifyAllergens(Request $request, MenuItem $item, VerifyAllergens $verify): RedirectResponse
    {
        Gate::authorize('verifyAllergens', $item);
        $verify->handle($request->user(), $item);

        return redirect()->route('admin.menu.edit', $item)->with('status', __('menu.allergens_verified'));
    }

    /**
     * @return array<string, string>
     */
    private function categoryOptions(): array
    {
        return MenuCategory::query()->with('translations')->orderBy('sort_order')->orderBy('id')->get()
            ->mapWithKeys(fn (MenuCategory $category) => [$category->id => $category->name()])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'categoryOptions' => $this->categoryOptions(),
            'allergenCodes' => config('restaurant.allergens'),
            'dietCodes' => config('restaurant.dietary_tags'),
            'relationOptions' => collect(AllergenRelation::cases())->mapWithKeys(fn (AllergenRelation $r) => [$r->value => $r->label()])->all(),
            'spiceOptions' => [0 => __('menu.spice.0'), 1 => __('menu.spice.1'), 2 => __('menu.spice.2'), 3 => __('menu.spice.3')],
        ];
    }
}
