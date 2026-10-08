<?php

namespace App\Modules\Menu\Data;

use App\Models\MenuItem;
use App\Modules\Menu\Enums\AllergenRelation;
use App\Modules\Menu\Support\Money;

/**
 * One retrieved dish (TECHNICAL_CONTRACTS evidence DTO). Allergen and
 * dietary data is only carried for verified dishes; for unverified dishes
 * both lists are empty and allergen_verified is false, so no consumer can
 * make an allergen or diet claim from this object.
 */
final class MenuEvidence
{
    public const MAX_DESCRIPTION = 1000;

    /**
     * @param  list<string>  $dietaryTags  Empty unless verified.
     * @param  array{contains: list<string>, may_contain: list<string>}  $allergens  Empty unless verified.
     */
    public function __construct(
        public readonly int $itemId,
        public readonly string $locale,
        public readonly string $slug,
        public readonly string $name,
        public readonly string $description,
        public readonly int $price,
        public readonly string $currency,
        public readonly string $categoryCode,
        public readonly string $categoryName,
        public readonly array $dietaryTags,
        public readonly array $allergens,
        public readonly bool $allergenVerified,
        public readonly bool $isAvailable,
        public readonly bool $isChefPick,
        public readonly ?int $spiceLevel,
        public readonly ?string $photoUrl,
        public readonly string $sourcePath,
        public readonly int $score = 0,
    ) {}

    /**
     * Builds evidence from a published dish with translations, category,
     * allergens, and dietary tags loaded (or loadable).
     */
    public static function fromItem(MenuItem $item, string $locale, int $score = 0): self
    {
        $item->loadMissing(['translations', 'category.translations', 'allergens', 'dietaryTags']);
        $translation = $item->translation($locale);
        $verified = $item->isVerified();

        return new self(
            itemId: $item->id,
            locale: $translation?->locale ?? $locale,
            slug: $item->slug,
            name: $translation?->name ?? '',
            description: $translation?->description ?? '',
            price: $item->price,
            currency: $item->currency,
            categoryCode: $item->category->code,
            categoryName: $item->category->name($locale),
            dietaryTags: $verified ? $item->dietaryTagCodes() : [],
            allergens: $verified
                ? ['contains' => $item->allergenCodes(AllergenRelation::Contains), 'may_contain' => $item->allergenCodes(AllergenRelation::MayContain)]
                : ['contains' => [], 'may_contain' => []],
            allergenVerified: $verified,
            isAvailable: $item->is_available,
            isChefPick: $item->is_chef_pick,
            spiceLevel: $item->spice_level,
            photoUrl: $item->photoUrl(),
            sourcePath: route('experience.menu.show', $item->slug, false),
            score: $score,
        );
    }

    /**
     * Hash of everything a guest could rely on (price, availability, allergen
     * and diet data, text). Stored with a turn's evidence; a later difference
     * marks the old answer as stale.
     */
    public function fingerprint(): string
    {
        return hash('sha256', (string) json_encode([
            $this->name, $this->description, $this->price, $this->currency, $this->isAvailable,
            $this->allergenVerified, $this->dietaryTags, $this->allergens,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function priceFormatted(?string $locale = null): string
    {
        return Money::format($this->price, $this->currency, $locale ?? $this->locale);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(int $descriptionLimit = self::MAX_DESCRIPTION): array
    {
        $description = mb_strlen($this->description) > $descriptionLimit
            ? rtrim(mb_substr($this->description, 0, $descriptionLimit - 1)).'…'
            : $this->description;

        return [
            'item_id' => $this->itemId,
            'locale' => $this->locale,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $description,
            'price' => $this->price,
            'price_formatted' => $this->priceFormatted(),
            'currency' => $this->currency,
            'category_code' => $this->categoryCode,
            'category_name' => $this->categoryName,
            'dietary_tags' => $this->dietaryTags,
            'allergens' => $this->allergens,
            'allergen_verified' => $this->allergenVerified,
            'is_available' => $this->isAvailable,
            'is_chef_pick' => $this->isChefPick,
            'spice_level' => $this->spiceLevel,
            'photo_url' => $this->photoUrl,
            'source_path' => $this->sourcePath,
        ];
    }
}
