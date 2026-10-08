<?php

namespace App\Modules\Assistant\Tools\Handlers;

use App\Modules\Knowledge\Data\KnowledgeEvidence;
use App\Modules\Menu\Data\MenuEvidence;

/**
 * What the model sees of a source. Only fields the model needs to answer and
 * cite (ids, text, price, verified allergen data); no URLs, photos, or
 * anything that is not already public on the menu or info page.
 */
final class ModelView
{
    /**
     * @return array<string, mixed>
     */
    public static function dish(MenuEvidence $item, int $descriptionLimit): array
    {
        $view = $item->toArray($descriptionLimit);

        return [
            'item_id' => $view['item_id'],
            'name' => $view['name'],
            'description' => $view['description'],
            'category' => $view['category_name'],
            'price' => $view['price_formatted'],
            'is_available' => $view['is_available'],
            'is_chef_pick' => $view['is_chef_pick'],
            'spice_level' => $view['spice_level'],
            'allergen_verified' => $view['allergen_verified'],
            // Empty unless verified: never a basis for an allergen or diet claim.
            'dietary_tags' => $view['dietary_tags'],
            'allergens' => $view['allergens'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function article(KnowledgeEvidence $item, int $excerptLimit): array
    {
        $view = $item->toArray($excerptLimit);

        return [
            'revision_id' => $view['revision_id'],
            'title' => $view['title'],
            'category' => $view['category'],
            'excerpt' => $view['excerpt'],
            'effective_from' => $view['effective_from'],
            'effective_until' => $view['effective_until'],
        ];
    }
}
