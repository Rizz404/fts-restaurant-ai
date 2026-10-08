<?php

namespace App\Modules\Menu\Validation;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Modules\Knowledge\Support\TextNormalizer;
use App\Modules\Menu\Enums\AllergenRelation;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Dish input rules shared by create and update. Drafts may be incomplete;
 * publicationErrors() decides whether a dish can be published.
 */
final class MenuItemRules
{
    public const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public const MAX_NAME = 120;

    public const MAX_DESCRIPTION = 1000;

    public const MAX_LIST_ITEMS = 50;

    public const MAX_LIST_ITEM_LENGTH = 80;

    public const MAX_PRICE = 100_000_000;

    /**
     * @return array<string, mixed>
     */
    public static function rules(?MenuItem $item): array
    {
        $creating = $item === null;
        $slugEditable = $creating || ! $item->slugIsLocked();

        $rules = [
            'slug' => $slugEditable
                ? ['nullable', 'string', 'max:120', 'regex:'.self::SLUG_PATTERN, Rule::unique('menu_items', 'slug')->ignore($item?->getKey())]
                : ['exclude'],
            'category_id' => [
                'required', 'integer',
                Rule::exists('menu_categories', 'id')->where(fn ($query) => $query->where('is_active', true)
                    ->when($item !== null, fn ($query) => $query->orWhere('id', $item->category_id))),
            ],
            'price' => ['nullable', 'integer', 'min:0', 'max:'.self::MAX_PRICE],
            'is_available' => ['boolean'],
            'is_chef_pick' => ['boolean'],
            'spice_level' => ['nullable', 'integer', 'min:0', 'max:3'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'translations' => ['required', 'array'],
            'allergens' => ['array'],
            'allergens.*' => ['nullable', Rule::in(['', ...AllergenRelation::values()])],
            'dietary_tags' => ['array'],
            'dietary_tags.*' => ['string', 'distinct', Rule::in(config('restaurant.dietary_tags'))],
            'photo' => ['nullable', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048', 'dimensions:min_width=800,min_height=600'],
            'remove_photo' => ['boolean'],
        ];

        foreach (config('restaurant.locales') as $locale) {
            $rules["translations.{$locale}.name"] = ['nullable', 'string', 'max:'.self::MAX_NAME];
            $rules["translations.{$locale}.description"] = ['nullable', 'string', 'max:'.self::MAX_DESCRIPTION];
            $rules["translations.{$locale}.aliases"] = ['nullable', 'string', 'max:6000'];
        }

        return $rules;
    }

    /**
     * Allergen keys must be known codes; alias lists are limited to 50
     * entries of 1–80 characters after normalization.
     */
    public static function validateExtras(Validator $validator): void
    {
        $data = $validator->getData();

        foreach (array_keys((array) ($data['allergens'] ?? [])) as $code) {
            if (! in_array($code, config('restaurant.allergens'), true)) {
                $validator->errors()->add('allergens', __('menu.errors.unknown_allergen'));
            }
        }

        foreach (config('restaurant.locales') as $locale) {
            $items = TextNormalizer::splitList($data['translations'][$locale]['aliases'] ?? '');
            $tooLong = array_filter($items, fn (string $alias) => mb_strlen($alias) > self::MAX_LIST_ITEM_LENGTH);

            if (count($items) > self::MAX_LIST_ITEMS || $tooLong !== []) {
                $validator->errors()->add("translations.{$locale}.aliases", __('menu.errors.list_limits', [
                    'max' => self::MAX_LIST_ITEMS,
                    'length' => self::MAX_LIST_ITEM_LENGTH,
                ]));
            }
        }
    }

    /**
     * Converts validated form data into the action payload.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public static function payload(array $validated): array
    {
        $translations = [];
        foreach (config('restaurant.locales') as $locale) {
            $input = $validated['translations'][$locale] ?? [];
            $translations[$locale] = [
                'name' => trim((string) ($input['name'] ?? '')),
                'description' => trim(str_replace("\r\n", "\n", (string) ($input['description'] ?? ''))),
                'aliases' => TextNormalizer::splitList($input['aliases'] ?? ''),
            ];
        }

        $allergens = [];
        foreach ((array) ($validated['allergens'] ?? []) as $code => $relation) {
            if (is_string($relation) && $relation !== '' && in_array($code, config('restaurant.allergens'), true)) {
                $allergens[$code] = $relation;
            }
        }
        ksort($allergens);

        $tags = array_values(array_unique((array) ($validated['dietary_tags'] ?? [])));
        sort($tags);

        return array_filter([
            'slug' => $validated['slug'] ?? null,
        ], fn ($value) => $value !== null && $value !== '') + [
            'category_id' => (int) $validated['category_id'],
            'price' => (int) ($validated['price'] ?? 0),
            'is_available' => (bool) ($validated['is_available'] ?? false),
            'is_chef_pick' => (bool) ($validated['is_chef_pick'] ?? false),
            'spice_level' => isset($validated['spice_level']) && $validated['spice_level'] !== '' ? (int) $validated['spice_level'] : null,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'translations' => $translations,
            'allergens' => $allergens,
            'dietary_tags' => $tags,
        ];
    }

    /**
     * Publication requirements (TECHNICAL_CONTRACTS): name and description in
     * every locale, price above zero, an active category, and an explicit
     * allergen decision (verification timestamp).
     *
     * @return array<string, string> field => message
     */
    public static function publicationErrors(MenuItem $item): array
    {
        $item->loadMissing('translations');
        $errors = [];

        foreach (config('restaurant.locales') as $locale) {
            $translation = $item->translations->firstWhere('locale', $locale);

            if ($translation === null || trim($translation->name) === '') {
                $errors["translations.{$locale}.name"] = __('menu.errors.name_required');
            }
            if ($translation === null || trim($translation->description) === '') {
                $errors["translations.{$locale}.description"] = __('menu.errors.description_required');
            }
        }

        if ($item->price <= 0) {
            $errors['price'] = __('menu.errors.price_required');
        }

        if (! MenuCategory::query()->whereKey($item->category_id)->where('is_active', true)->exists()) {
            $errors['category_id'] = __('menu.errors.category_inactive');
        }

        if (! $item->isVerified()) {
            $errors['allergen_verification'] = __('menu.errors.verification_required');
        }

        return $errors;
    }

    /**
     * Per-locale completeness for the CMS indicator.
     *
     * @return array<string, bool>
     */
    public static function completeness(MenuItem $item): array
    {
        $item->loadMissing('translations');
        $result = [];

        foreach (config('restaurant.locales') as $locale) {
            $translation = $item->translations->firstWhere('locale', $locale);
            $result[$locale] = $translation !== null && $translation->isComplete();
        }

        return $result;
    }
}
