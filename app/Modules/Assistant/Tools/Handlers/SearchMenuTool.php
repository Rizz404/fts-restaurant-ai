<?php

namespace App\Modules\Assistant\Tools\Handlers;

use App\Models\MenuCategory;
use App\Modules\Assistant\Evidence\EvidencePool;
use App\Modules\Assistant\Tools\ToolArguments;
use App\Modules\Assistant\Tools\ToolContext;
use App\Modules\Assistant\Tools\ToolResult;
use App\Modules\Identity\Guest\GuestContext;
use App\Modules\Menu\Data\MenuEvidence;
use App\Modules\Menu\Data\MenuFilters;
use App\Modules\Menu\MenuRetriever;
use Illuminate\Validation\Rule;

/**
 * Searches published dishes with the same deterministic retriever as the
 * menu page. The guest's saved preferences (diet and allergens to avoid)
 * are merged in by the server, so the model cannot drop an allergen filter
 * the guest chose. Dishes with unverified allergen data never match when a
 * diet or allergen filter is active.
 */
class SearchMenuTool extends BaseTool
{
    public function __construct(private readonly MenuRetriever $menu) {}

    public function name(): string
    {
        return 'search_menu';
    }

    protected function description(): string
    {
        return 'Search the restaurant menu for dishes (recommendations, ingredients, price, category, diet, allergens to avoid). Only published dishes are returned. The guest\'s saved diet and allergen preferences are always applied.';
    }

    protected function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'minLength' => 1, 'maxLength' => MenuFilters::MAX_QUERY_LENGTH, 'description' => 'Words describing the dish, in any language.'],
                'category' => ['type' => 'string', 'enum' => $this->categoryCodes(), 'description' => 'Menu category code.'],
                'dietary_tags' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => config('restaurant.dietary_tags')], 'maxItems' => 5],
                'exclude_allergens' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => config('restaurant.allergens')], 'maxItems' => 15],
                'max_price' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Maximum price in the menu currency.'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => MenuRetriever::MAX_RESULTS],
            ],
        ];
    }

    public function validate(GuestContext $guest, array $arguments): array
    {
        return ToolArguments::validate($arguments, [
            'query' => ['sometimes', 'nullable', 'string', 'min:1', 'max:'.MenuFilters::MAX_QUERY_LENGTH],
            'category' => ['sometimes', 'nullable', 'string', Rule::in($this->categoryCodes())],
            'dietary_tags' => ['sometimes', 'array', 'max:5'],
            'dietary_tags.*' => ['string', Rule::in(config('restaurant.dietary_tags'))],
            'exclude_allergens' => ['sometimes', 'array', 'max:15'],
            'exclude_allergens.*' => ['string', Rule::in(config('restaurant.allergens'))],
            'max_price' => ['sometimes', 'nullable', 'integer:strict', 'min:1'],
            'limit' => ['sometimes', 'integer:strict', 'min:1', 'max:'.MenuRetriever::MAX_RESULTS],
        ]);
    }

    public function execute(GuestContext $guest, array $arguments, ToolContext $context): ToolResult
    {
        $preferences = $context->preferences ?? ['dietary_tags' => [], 'exclude_allergens' => []];

        // Server-side union: the model's filters can add to the guest's preferences, never remove them.
        $filters = MenuFilters::fromArray([
            'query' => $arguments['query'] ?? null,
            'category' => $arguments['category'] ?? null,
            'dietary_tags' => array_merge($arguments['dietary_tags'] ?? [], $preferences['dietary_tags'] ?? []),
            'exclude_allergens' => array_merge($arguments['exclude_allergens'] ?? [], $preferences['exclude_allergens'] ?? []),
            'max_price' => $arguments['max_price'] ?? null,
        ]);

        $limit = (int) ($arguments['limit'] ?? MenuRetriever::MAX_RESULTS);
        $found = $this->menu->search($context->locale, $filters, $limit);

        $pool = new EvidencePool;
        foreach ($found->items as $item) {
            $pool->addMenu($item);
        }
        $pool->noteUnverifiedExcluded($found->unverifiedExcludedCount);

        return new ToolResult(
            ToolResult::OK,
            [
                'count' => count($found->items),
                'applied_filters' => $filters->toArray(),
                'unverified_dishes_withheld' => $found->unverifiedExcludedCount,
                'dishes' => array_map(fn (MenuEvidence $item) => ModelView::dish($item, 300), $found->items),
            ],
            evidence: $pool,
            reference: ['count' => count($found->items)],
        );
    }

    public function loggableInput(array $arguments): array
    {
        return parent::loggableInput($arguments) + [
            'category' => $arguments['category'] ?? null,
            'dietary_tags' => $arguments['dietary_tags'] ?? [],
            'exclude_allergens' => $arguments['exclude_allergens'] ?? [],
        ];
    }

    /**
     * @return list<string>
     */
    private function categoryCodes(): array
    {
        return MenuCategory::query()->active()->orderBy('sort_order')->pluck('code')->all();
    }
}
