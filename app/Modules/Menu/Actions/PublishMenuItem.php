<?php

namespace App\Modules\Menu\Actions;

use App\Models\MenuItem;
use App\Models\User;
use App\Modules\Identity\Actions\WriteAuditEvent;
use App\Modules\Menu\Enums\MenuStatus;
use App\Modules\Menu\Support\MenuAuthorization;
use App\Modules\Menu\Validation\MenuItemRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Publishes a draft or archived dish immediately. Requires every locale,
 * a positive price, an active category, and verified allergen data.
 */
class PublishMenuItem
{
    public function __construct(private readonly WriteAuditEvent $audit) {}

    public function handle(User $actor, MenuItem $item): MenuItem
    {
        return DB::transaction(function () use ($actor, $item) {
            MenuAuthorization::assertCanManage($actor);

            /** @var MenuItem $item */
            $item = MenuItem::query()->whereKey($item->getKey())->lockForUpdate()->firstOrFail();

            if ($item->isPublished()) {
                return $item;
            }

            $errors = MenuItemRules::publicationErrors($item);
            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            $item->forceFill([
                'status' => MenuStatus::Published,
                'first_published_at' => $item->first_published_at ?? now(),
            ])->save();

            $this->audit->handle($actor, 'menu.published', $item, ['slug' => $item->slug]);

            return $item;
        });
    }
}
