<?php

namespace App\Modules\Menu\Actions;

use App\Models\MenuCategory;
use App\Models\MenuCategoryTranslation;
use App\Models\User;
use App\Modules\Identity\Actions\WriteAuditEvent;
use App\Modules\Menu\Support\MenuAuthorization;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates a menu category. Categories are never deleted
 * (deactivate instead); the code is fixed after creation.
 */
class SaveCategory
{
    public function __construct(private readonly WriteAuditEvent $audit) {}

    /**
     * @param  array{code?: string, sort_order: int, is_active: bool, names: array<string, string>}  $data
     */
    public function handle(User $actor, ?MenuCategory $category, array $data): MenuCategory
    {
        return DB::transaction(function () use ($actor, $category, $data) {
            MenuAuthorization::assertCanManage($actor);

            if ($category === null) {
                $category = MenuCategory::create([
                    'code' => $data['code'],
                    'sort_order' => $data['sort_order'],
                    'is_active' => $data['is_active'],
                ]);
                $action = 'menu.category_created';
            } else {
                $category = MenuCategory::query()->whereKey($category->getKey())->lockForUpdate()->firstOrFail();
                $category->fill(['sort_order' => $data['sort_order'], 'is_active' => $data['is_active']])->save();
                $action = 'menu.category_updated';
            }

            foreach ($data['names'] as $locale => $name) {
                MenuCategoryTranslation::query()->updateOrCreate(
                    ['category_id' => $category->id, 'locale' => $locale],
                    ['name' => $name],
                );
            }

            $this->audit->handle($actor, $action, $category, ['code' => $category->code, 'is_active' => $category->is_active]);

            return $category->load('translations');
        });
    }
}
