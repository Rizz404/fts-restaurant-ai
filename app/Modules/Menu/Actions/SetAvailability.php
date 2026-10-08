<?php

namespace App\Modules\Menu\Actions;

use App\Models\MenuItem;
use App\Models\User;
use App\Modules\Identity\Actions\WriteAuditEvent;
use App\Modules\Menu\Support\MenuAuthorization;
use Illuminate\Support\Facades\DB;

/**
 * Marks a dish available or sold out. Open to staff of any team as well as
 * admin and editor; applies immediately and is audited.
 */
class SetAvailability
{
    public function __construct(private readonly WriteAuditEvent $audit) {}

    public function handle(User $actor, MenuItem $item, bool $available): MenuItem
    {
        return DB::transaction(function () use ($actor, $item, $available) {
            MenuAuthorization::assertCanSetAvailability($actor);

            /** @var MenuItem $item */
            $item = MenuItem::query()->whereKey($item->getKey())->lockForUpdate()->firstOrFail();

            if ($item->is_available === $available) {
                return $item;
            }

            $item->forceFill(['is_available' => $available])->save();

            $this->audit->handle($actor, 'menu.availability_changed', $item, ['from' => ! $available, 'to' => $available]);

            return $item;
        });
    }
}
