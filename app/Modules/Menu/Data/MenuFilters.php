<?php

namespace App\Modules\Menu\Data;

use App\Modules\Knowledge\Support\TextNormalizer;

/**
 * Menu search filters. Codes are whitelisted against config/restaurant.php;
 * unknown codes are dropped so callers (pages, API, AI tools) share one
 * sanitized shape.
 */
final class MenuFilters
{
    public const MAX_QUERY_LENGTH = 200;

    /**
     * @param  list<string>  $dietaryTags  Every tag must match (item must be verified).
     * @param  list<string>  $excludeAllergens  Items containing or possibly containing any of these are removed, as are unverified items.
     */
    public function __construct(
        public readonly ?string $query = null,
        public readonly ?string $category = null,
        public readonly array $dietaryTags = [],
        public readonly array $excludeAllergens = [],
        public readonly ?int $maxPrice = null,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        $query = isset($input['query']) && is_string($input['query']) ? mb_substr(trim($input['query']), 0, self::MAX_QUERY_LENGTH) : null;
        $category = isset($input['category']) && is_string($input['category']) && $input['category'] !== '' ? $input['category'] : null;

        $diet = array_values(array_unique(array_filter(
            (array) ($input['dietary_tags'] ?? []),
            fn ($tag) => is_string($tag) && in_array($tag, config('restaurant.dietary_tags'), true),
        )));
        $allergens = array_values(array_unique(array_filter(
            (array) ($input['exclude_allergens'] ?? []),
            fn ($code) => is_string($code) && in_array($code, config('restaurant.allergens'), true),
        )));
        sort($diet);
        sort($allergens);

        $max = $input['max_price'] ?? null;

        return new self(
            query: $query === '' ? null : $query,
            category: $category,
            dietaryTags: $diet,
            excludeAllergens: $allergens,
            maxPrice: is_numeric($max) && (int) $max > 0 ? (int) $max : null,
        );
    }

    public function normalizedQuery(): string
    {
        return TextNormalizer::normalize((string) $this->query);
    }

    public function hasSafetyFilters(): bool
    {
        return $this->dietaryTags !== [] || $this->excludeAllergens !== [];
    }

    public function isEmpty(): bool
    {
        return $this->query === null && $this->category === null && $this->dietaryTags === [] && $this->excludeAllergens === [] && $this->maxPrice === null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'query' => $this->query,
            'category' => $this->category,
            'dietary_tags' => $this->dietaryTags,
            'exclude_allergens' => $this->excludeAllergens,
            'max_price' => $this->maxPrice,
        ];
    }
}
