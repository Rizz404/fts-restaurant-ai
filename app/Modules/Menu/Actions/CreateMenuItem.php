<?php

namespace App\Modules\Menu\Actions;

use App\Models\MenuItem;
use App\Models\User;
use App\Modules\Identity\Actions\WriteAuditEvent;
use App\Modules\Menu\Enums\MenuStatus;
use App\Modules\Menu\Support\MenuAuthorization;
use App\Modules\Menu\Support\MenuItemWriter;
use App\Modules\Menu\Support\MenuPhotos;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates a dish as a draft. The allergen verification is always empty on a
 * new dish; the slug is generated from the Indonesian name when not given.
 */
class CreateMenuItem
{
    public function __construct(
        private readonly WriteAuditEvent $audit,
        private readonly MenuPhotos $photos,
    ) {}

    /**
     * @param  array<string, mixed>  $payload  From MenuItemRules::payload().
     */
    public function handle(User $actor, array $payload, ?UploadedFile $photo = null): MenuItem
    {
        $photoPath = null;

        try {
            return DB::transaction(function () use ($actor, $payload, $photo, &$photoPath) {
                MenuAuthorization::assertCanManage($actor);

                $slug = $payload['slug'] ?? $this->uniqueSlug((string) ($payload['translations']['id']['name'] ?? ''));

                $item = MenuItem::create([
                    'slug' => $slug,
                    'category_id' => $payload['category_id'],
                    'status' => MenuStatus::Draft,
                    'price' => $payload['price'],
                    'currency' => config('restaurant.currency'),
                    'is_available' => $payload['is_available'],
                    'is_chef_pick' => $payload['is_chef_pick'],
                    'spice_level' => $payload['spice_level'],
                    'sort_order' => $payload['sort_order'],
                    'created_by' => $actor->id,
                ]);

                MenuItemWriter::writeTranslations($item, $payload['translations']);
                MenuItemWriter::writeAllergenData($item, $payload['allergens'], $payload['dietary_tags']);

                if ($photo !== null) {
                    $photoPath = $this->photos->store($photo, $item->slug);
                    $item->forceFill(['photo_path' => $photoPath])->save();
                }

                $this->audit->handle($actor, 'menu.item_created', $item, ['slug' => $item->slug]);

                return $item;
            });
        } catch (\Throwable $e) {
            $this->photos->delete($photoPath);

            throw $e;
        }
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $base = $base === '' ? 'dish' : Str::limit($base, 100, '');
        $slug = $base;
        $suffix = 2;

        while (MenuItem::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
