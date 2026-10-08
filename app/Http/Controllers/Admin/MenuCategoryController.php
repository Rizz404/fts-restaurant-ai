<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MenuCategoryRequest;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Modules\Menu\Actions\SaveCategory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Menu categories (admin and editor). No delete: deactivate instead.
 */
class MenuCategoryController extends Controller
{
    public function index(): View
    {
        Gate::authorize('manageCategories', MenuItem::class);

        return view('admin.menu.categories.index', [
            'categories' => MenuCategory::query()->with('translations')->withCount('items')->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('manageCategories', MenuItem::class);

        return view('admin.menu.categories.create');
    }

    public function store(MenuCategoryRequest $request, SaveCategory $save): RedirectResponse
    {
        $save->handle($request->user(), null, $request->payload());

        return redirect()->route('admin.menu.categories.index')->with('status', __('menu.category_created'));
    }

    public function edit(MenuCategory $category): View
    {
        Gate::authorize('manageCategories', MenuItem::class);

        return view('admin.menu.categories.edit', ['category' => $category->load('translations')]);
    }

    public function update(MenuCategoryRequest $request, MenuCategory $category, SaveCategory $save): RedirectResponse
    {
        $save->handle($request->user(), $category, $request->payload());

        return redirect()->route('admin.menu.categories.index')->with('status', __('menu.category_updated'));
    }
}
