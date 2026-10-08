<?php

namespace App\Modules\Menu\Actions;

use App\Models\MenuItem;
use App\Models\User;
use App\Modules\Identity\Actions\WriteAuditEvent;
use App\Modules\Menu\Support\MenuAuthorization;
use App\Modules\Menu\Support\MenuItemWriter;
use App\Modules\Menu\Support\MenuPhotos;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Edits a dish in place (draft, published, or archived). Price, availability,
 * allergen, and diet changes apply immediately and are audited with old and
 * new values. Any change to the allergen list or dietary tags clears the
 * allergen verification until staff verify again.
 */
class UpdateMenuItem
{
    public function __construct(
        private readonly WriteAuditEvent $audit,
        private readonly MenuPhotos $photos,
    ) {}

    /**
     * @param  array<string, mixed>  $payload  From MenuItemRules::payload().
     */
    public function handle(User $actor, MenuItem $item, array $payload, ?UploadedFile $photo = null, bool $removePhoto = false): MenuItem
    {
        $newPath = null;
        $oldPath = null;

        try {
            $updated = DB::transaction(function () use ($actor, $item, $payload, $photo, $removePhoto, &$newPath, &$oldPath) {
                MenuAuthorization::assertCanManage($actor);

                /** @var MenuItem $item */
                $item = MenuItem::query()->whereKey($item->getKey())->lockForUpdate()->firstOrFail();
                $before = $item->only(['price', 'is_available']);

                $fields = [
                    'category_id' => $payload['category_id'],
                    'price' => $payload['price'],
                    'is_available' => $payload['is_available'],
                    'is_chef_pick' => $payload['is_chef_pick'],
                    'spice_level' => $payload['spice_level'],
                    'sort_order' => $payload['sort_order'],
                ];

                if (isset($payload['slug']) && ! $item->slugIsLocked()) {
                    $fields['slug'] = $payload['slug'];
                }

                $item->fill($fields)->save();
                MenuItemWriter::writeTranslations($item, $payload['translations']);

                $allergenChange = MenuItemWriter::writeAllergenData($item, $payload['allergens'], $payload['dietary_tags']);
                if ($allergenChange !== null) {
                    $item->forceFill(['allergen_info_verified_at' => null, 'allergen_info_verified_by' => null])->save();
                    $this->audit->handle($actor, 'menu.allergens_changed', $item, $allergenChange + ['verification_cleared' => true]);
                }

                if ($before['price'] !== $item->price) {
                    $this->audit->handle($actor, 'menu.price_changed', $item, ['from' => $before['price'], 'to' => $item->price]);
                }

                if ($before['is_available'] !== $item->is_available) {
                    $this->audit->handle($actor, 'menu.availability_changed', $item, ['from' => $before['is_available'], 'to' => $item->is_available]);
                }

                if ($photo !== null || $removePhoto) {
                    $oldPath = $item->photo_path;
                    $newPath = $photo !== null ? $this->photos->store($photo, $item->slug) : null;
                    $item->forceFill(['photo_path' => $newPath])->save();
                }

                return $item;
            });
        } catch (\Throwable $e) {
            $this->photos->delete($newPath);

            throw $e;
        }

        // The old file is only removed once the new state is committed.
        if ($oldPath !== null && $oldPath !== $newPath) {
            $this->photos->delete($oldPath);
        }

        return $updated;
    }
}
