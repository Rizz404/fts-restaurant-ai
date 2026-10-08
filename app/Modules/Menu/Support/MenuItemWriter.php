<?php

namespace App\Modules\Menu\Support;

use App\Models\MenuItem;
use App\Models\MenuItemTranslation;

/**
 * Writes translations, allergens, and dietary tags of a dish and reports
 * what changed so the caller can audit and clear the allergen verification.
 */
final class MenuItemWriter
{
    /**
     * @param  array<string, array{name: string, description: string, aliases: list<string>}>  $translations
     */
    public static function writeTranslations(MenuItem $item, array $translations): void
    {
        foreach ($translations as $locale => $translation) {
            MenuItemTranslation::query()->updateOrCreate(
                ['item_id' => $item->id, 'locale' => $locale],
                $translation,
            );
        }
    }

    /**
     * Replaces allergens and dietary tags. Returns the previous and new
     * values when either list changed, otherwise null.
     *
     * @param  array<string, string>  $allergens  allergen code => relation
     * @param  list<string>  $dietaryTags
     * @return array{allergens: array{from: array<string, string>, to: array<string, string>}, dietary_tags: array{from: list<string>, to: list<string>}}|null
     */
    public static function writeAllergenData(MenuItem $item, array $allergens, array $dietaryTags): ?array
    {
        $item->unsetRelation('allergens')->unsetRelation('dietaryTags');

        $oldAllergens = [];
        foreach ($item->allergens()->get() as $row) {
            $oldAllergens[$row->allergen_code] = $row->relation->value;
        }
        ksort($oldAllergens);

        $oldTags = $item->dietaryTags()->pluck('tag')->sort()->values()->all();

        if ($oldAllergens === $allergens && $oldTags === $dietaryTags) {
            return null;
        }

        $item->allergens()->delete();
        foreach ($allergens as $code => $relation) {
            $item->allergens()->create(['allergen_code' => $code, 'relation' => $relation]);
        }

        $item->dietaryTags()->delete();
        foreach ($dietaryTags as $tag) {
            $item->dietaryTags()->create(['tag' => $tag]);
        }

        $item->unsetRelation('allergens')->unsetRelation('dietaryTags');

        return [
            'allergens' => ['from' => $oldAllergens, 'to' => $allergens],
            'dietary_tags' => ['from' => $oldTags, 'to' => $dietaryTags],
        ];
    }
}
