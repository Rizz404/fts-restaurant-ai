<?php

namespace App\Modules\Menu\Actions;

use App\Models\MenuItem;
use App\Models\User;
use App\Modules\Identity\Actions\WriteAuditEvent;
use App\Modules\Menu\Support\MenuAuthorization;
use Illuminate\Support\Facades\DB;

/**
 * Staff (admin or editor) declare that the allergen list and dietary tags of
 * a dish are correct, including an empty allergen list. Until then the AI and
 * the menu make no allergen or diet claims for the dish.
 */
class VerifyAllergens
{
    public function __construct(private readonly WriteAuditEvent $audit) {}

    public function handle(User $actor, MenuItem $item): MenuItem
    {
        return DB::transaction(function () use ($actor, $item) {
            MenuAuthorization::assertCanManage($actor);

            /** @var MenuItem $item */
            $item = MenuItem::query()->whereKey($item->getKey())->lockForUpdate()->firstOrFail();

            $item->forceFill([
                'allergen_info_verified_at' => now(),
                'allergen_info_verified_by' => $actor->id,
            ])->save();

            $this->audit->handle($actor, 'menu.allergen_verified', $item, [
                'allergens' => $item->allergens()->get()->mapWithKeys(fn ($row) => [$row->allergen_code => $row->relation->value])->sortKeys()->all(),
                'dietary_tags' => $item->dietaryTags()->pluck('tag')->sort()->values()->all(),
            ]);

            return $item;
        });
    }
}
