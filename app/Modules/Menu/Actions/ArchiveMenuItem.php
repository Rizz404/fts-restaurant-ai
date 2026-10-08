<?php

namespace App\Modules\Menu\Actions;

use App\Models\MenuItem;
use App\Models\User;
use App\Modules\Identity\Actions\WriteAuditEvent;
use App\Modules\Menu\Enums\MenuStatus;
use App\Modules\Menu\Support\MenuAuthorization;
use Illuminate\Support\Facades\DB;

/**
 * Withdraws a dish from the menu and from retrieval. The record is kept so
 * earlier requests that reference it stay valid.
 */
class ArchiveMenuItem
{
    public function __construct(private readonly WriteAuditEvent $audit) {}

    public function handle(User $actor, MenuItem $item): MenuItem
    {
        return DB::transaction(function () use ($actor, $item) {
            MenuAuthorization::assertCanManage($actor);

            /** @var MenuItem $item */
            $item = MenuItem::query()->whereKey($item->getKey())->lockForUpdate()->firstOrFail();

            if ($item->status === MenuStatus::Archived) {
                return $item;
            }

            $item->forceFill(['status' => MenuStatus::Archived])->save();

            $this->audit->handle($actor, 'menu.archived', $item, ['slug' => $item->slug]);

            return $item;
        });
    }
}
