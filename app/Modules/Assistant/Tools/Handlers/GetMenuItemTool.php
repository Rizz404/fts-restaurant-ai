<?php

namespace App\Modules\Assistant\Tools\Handlers;

use App\Modules\Assistant\Evidence\EvidencePool;
use App\Modules\Assistant\Tools\ToolArguments;
use App\Modules\Assistant\Tools\ToolContext;
use App\Modules\Assistant\Tools\ToolResult;
use App\Modules\Identity\Guest\GuestContext;
use App\Modules\Menu\MenuRetriever;

/**
 * One published dish with its full detail, including the allergen
 * verification status. An unpublished or unknown id is "not found".
 */
class GetMenuItemTool extends BaseTool
{
    public function __construct(private readonly MenuRetriever $menu) {}

    public function name(): string
    {
        return 'get_menu_item';
    }

    protected function description(): string
    {
        return 'Get the full detail of one dish by its item_id (price, availability, verified allergen and diet data, description).';
    }

    protected function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'item_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'The item_id of a dish from a previous result or the current screen.'],
            ],
            'required' => ['item_id'],
        ];
    }

    public function validate(GuestContext $guest, array $arguments): array
    {
        return ToolArguments::validate($arguments, [
            'item_id' => ['required', 'integer:strict', 'min:1'],
        ]);
    }

    public function execute(GuestContext $guest, array $arguments, ToolContext $context): ToolResult
    {
        $item = $this->menu->findEvidence((int) $arguments['item_id'], $context->locale);

        if ($item === null) {
            return ToolResult::error(ToolResult::NOT_FOUND, ['error' => 'dish_not_found']);
        }

        $pool = new EvidencePool;
        $pool->addMenu($item);

        return new ToolResult(
            ToolResult::OK,
            ['dish' => ModelView::dish($item, $item::MAX_DESCRIPTION)],
            evidence: $pool,
            reference: ['item_id' => $item->itemId],
        );
    }

    public function loggableInput(array $arguments): array
    {
        return ['item_id' => $arguments['item_id'] ?? null];
    }
}
