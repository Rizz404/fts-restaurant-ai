<?php

namespace App\Modules\Menu\Data;

/**
 * Result of a menu search: evidence in display order, and how many dishes
 * were withheld only because their allergen data is not verified (so the AI
 * can say that part of the menu could not be assessed).
 */
final class MenuSearchResult
{
    /**
     * @param  list<MenuEvidence>  $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $unverifiedExcludedCount = 0,
    ) {}
}
