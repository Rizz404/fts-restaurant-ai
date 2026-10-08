<?php

namespace App\Modules\Assistant\Tools\Handlers;

use App\Modules\Requests\Enums\RequestType;
use App\Modules\Requests\Validation\RequestDraftRules;

/**
 * Draft of a takeaway or dine-in pre-order. Prices come from the database
 * when the draft is shown and when it is confirmed; the model supplies only
 * dish ids and quantities.
 */
class PrepareOrderTool extends PrepareDraftTool
{
    public function name(): string
    {
        return 'prepare_order';
    }

    protected function description(): string
    {
        return 'Prepare a DRAFT order (pickup or dine-in pre-order for today) from dishes the guest chose. Use the item_id values from menu results. It is not sent: the guest reviews it, adds contact details, and confirms with a button. Never invent dishes, quantities, or a time; ask first. Prices are set by the server.';
    }

    protected function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'fulfilment' => ['type' => 'string', 'enum' => ['pickup', 'dine_in_preorder']],
                'requested_time' => ['type' => 'string', 'pattern' => '^\d{2}:\d{2}$', 'description' => 'Time today as HH:MM.'],
                'items' => [
                    'type' => 'array',
                    'minItems' => 1,
                    'maxItems' => RequestDraftRules::MAX_ITEMS,
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'menu_item_id' => ['type' => 'integer', 'minimum' => 1],
                            'quantity' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20],
                            'note' => ['type' => 'string', 'maxLength' => 200],
                        ],
                        'required' => ['menu_item_id', 'quantity'],
                        'additionalProperties' => false,
                    ],
                ],
                'notes' => ['type' => 'string', 'maxLength' => 500],
            ],
            'required' => ['fulfilment', 'requested_time', 'items'],
        ];
    }

    protected function rules(): array
    {
        return [
            'fulfilment' => ['required', 'string', 'in:pickup,dine_in_preorder'],
            'requested_time' => ['required', 'string', 'date_format:H:i'],
            'items' => ['required', 'array', 'min:1', 'max:'.RequestDraftRules::MAX_ITEMS],
            'items.*' => ['array:menu_item_id,quantity,note'],
            'items.*.menu_item_id' => ['required', 'integer:strict', 'min:1'],
            'items.*.quantity' => ['required', 'integer:strict', 'min:1', 'max:20'],
            'items.*.note' => ['sometimes', 'nullable', 'string', 'max:200'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    protected function requestType(array $arguments): RequestType
    {
        return RequestType::Order;
    }

    protected function payload(array $arguments): array
    {
        return [
            'fulfilment' => $arguments['fulfilment'],
            'requested_time' => $arguments['requested_time'],
            'items' => array_values(array_map(fn (array $item) => [
                'menu_item_id' => $item['menu_item_id'],
                'quantity' => $item['quantity'],
                'note' => $item['note'] ?? null,
            ], $arguments['items'])),
            'notes' => $arguments['notes'] ?? null,
        ];
    }
}
