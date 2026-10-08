<?php

namespace App\Modules\Conversation\Support;

use App\Models\MenuItem;
use App\Modules\Assistant\Evidence\EvidencePool;
use App\Modules\Experience\Enums\SceneCode;
use App\Modules\Knowledge\Data\KnowledgeEvidence;
use App\Modules\Knowledge\Queries\VisibleRevisions;
use App\Modules\Menu\Data\MenuEvidence;
use App\Modules\Menu\MenuRetriever;

/**
 * Server-built UI descriptors. Only whitelisted types; paths are named
 * routes resolved by the server, never URLs written by the model. Dish
 * cards are always built from canonical data (price, availability, and
 * allergen data are never copied from model text) and are re-read when a
 * past message is shown (refresh()).
 */
final class TurnCards
{
    public const MAX_DESCRIPTION = 160;

    /**
     * @param  list<array<string, mixed>>  $actions
     * @return array{sources: list<array<string, mixed>>, ui: list<array<string, mixed>>, actions: list<array<string, mixed>>}
     */
    public static function forPool(EvidencePool $pool, array $actions = []): array
    {
        $sources = [];
        $cards = [];

        $menu = $pool->menu();
        foreach ($menu as $item) {
            $sources[] = ['type' => 'menu_item', 'item_id' => $item->itemId, 'name' => $item->name, 'path' => $item->sourcePath];
        }
        if (count($menu) === 1) {
            $cards[] = ['type' => 'menu_item_card'] + self::menuCard($menu[0]);
        } elseif (count($menu) > 1) {
            $cards[] = ['type' => 'menu_list', 'locale' => $menu[0]->locale, 'items' => array_map(fn (MenuEvidence $item) => self::menuCard($item), $menu)];
        }

        foreach ($pool->info() as $item) {
            $source = [
                'article_id' => $item->articleId,
                'revision_id' => $item->revisionId,
                'version' => $item->version,
                'title' => $item->title,
                'effective_from' => $item->effectiveFrom?->toIso8601String(),
                'effective_until' => $item->effectiveUntil?->toIso8601String(),
                'path' => $item->sourcePath,
            ];
            $sources[] = ['type' => 'info_revision'] + $source;
            $cards[] = ['type' => 'source_card'] + $source;
        }

        return ['sources' => $sources, 'ui' => $cards, 'actions' => $actions];
    }

    /**
     * Card data for one dish. Allergen and diet lists are empty unless the
     * dish is verified (MenuEvidence already guarantees this).
     *
     * @return array<string, mixed>
     */
    public static function menuCard(MenuEvidence $item): array
    {
        $description = mb_strlen($item->description) > self::MAX_DESCRIPTION
            ? rtrim(mb_substr($item->description, 0, self::MAX_DESCRIPTION - 1)).'…'
            : $item->description;

        return [
            'item_id' => $item->itemId,
            'locale' => $item->locale,
            'slug' => $item->slug,
            'name' => $item->name,
            'description' => $description,
            'price' => $item->price,
            'price_formatted' => $item->priceFormatted(),
            'currency' => $item->currency,
            'category_name' => $item->categoryName,
            'is_available' => $item->isAvailable,
            'is_chef_pick' => $item->isChefPick,
            'dietary_tags' => $item->dietaryTags,
            'allergen_verified' => $item->allergenVerified,
            'allergens' => $item->allergens,
            'photo_path' => $item->photoUrl === null ? null : parse_url($item->photoUrl, PHP_URL_PATH),
            'path' => $item->sourcePath,
        ];
    }

    /**
     * Re-reads canonical data for dish and info cards of a stored message:
     * unpublished dishes and unavailable sources are dropped, and price,
     * availability, and allergen data reflect the database now.
     *
     * @param  array<string, mixed>  $ui  The stored {sources, ui, actions, ...} descriptor.
     * @return array<string, mixed>
     */
    public static function refresh(array $ui): array
    {
        $cards = $ui['ui'] ?? [];
        if (! is_array($cards) || $cards === []) {
            return $ui;
        }

        $retriever = app(MenuRetriever::class);
        $itemIds = [];
        $revisionIds = [];
        foreach ($cards as $card) {
            if (($card['type'] ?? null) === 'menu_item_card') {
                $itemIds[] = (int) ($card['item_id'] ?? 0);
            } elseif (($card['type'] ?? null) === 'menu_list') {
                foreach ((array) ($card['items'] ?? []) as $item) {
                    $itemIds[] = (int) ($item['item_id'] ?? 0);
                }
            } elseif (($card['type'] ?? null) === 'source_card') {
                $revisionIds[] = (int) ($card['revision_id'] ?? 0);
            }
        }

        $fresh = [];
        $locale = app()->getLocale();
        foreach (array_unique(array_filter($itemIds)) as $id) {
            $evidence = $retriever->findEvidence($id, $locale);
            if ($evidence !== null) {
                $fresh[$id] = self::menuCard($evidence);
            }
        }
        $visible = $revisionIds === []
            ? []
            : VisibleRevisions::query()->whereIn('knowledge_revisions.id', array_unique($revisionIds))->pluck('knowledge_revisions.id')->map(fn ($id) => (int) $id)->all();

        $result = [];
        foreach ($cards as $card) {
            $type = $card['type'] ?? null;
            if ($type === 'menu_item_card') {
                if (isset($fresh[(int) $card['item_id']])) {
                    $result[] = ['type' => 'menu_item_card'] + $fresh[(int) $card['item_id']];
                }
            } elseif ($type === 'menu_list') {
                $items = array_values(array_filter(array_map(fn ($item) => $fresh[(int) ($item['item_id'] ?? 0)] ?? null, (array) $card['items'])));
                if ($items !== []) {
                    $result[] = ['type' => 'menu_list', 'locale' => $locale, 'items' => $items];
                }
            } elseif ($type === 'source_card') {
                if (in_array((int) ($card['revision_id'] ?? 0), $visible, true)) {
                    $result[] = $card;
                }
            } else {
                $result[] = $card;
            }
        }

        $ui['ui'] = $result;

        return $ui;
    }

    /**
     * @return array<string, mixed>
     */
    public static function openScene(SceneCode $scene): array
    {
        return [
            'type' => 'open_scene',
            'scene' => $scene->value,
            'label' => $scene->label(),
            'path' => route($scene->routeName(), absolute: false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function openMenuItem(MenuEvidence $item): array
    {
        return ['type' => 'open_menu_item', 'label' => __('deck.chat.cards.open_item'), 'path' => $item->sourcePath];
    }

    /**
     * @return array<string, mixed>
     */
    public static function openArticle(KnowledgeEvidence $item): array
    {
        return ['type' => 'open_article', 'label' => __('deck.chat.cards.open_article'), 'path' => $item->sourcePath];
    }

    /**
     * Suggested next steps when no official source answered the question:
     * the guest can browse the menu or the info page without AI.
     *
     * @return list<array<string, mixed>>
     */
    public static function helpActions(): array
    {
        return [
            self::openScene(SceneCode::Menu),
            self::openScene(SceneCode::Info),
        ];
    }

    public static function stillPublished(int $itemId): bool
    {
        return MenuItem::query()->published()->whereKey($itemId)->exists();
    }
}
