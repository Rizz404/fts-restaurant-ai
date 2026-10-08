<?php

namespace App\Modules\Assistant\Tools\Handlers;

use App\Models\GuestRequest;
use App\Modules\Assistant\Tools\ToolArguments;
use App\Modules\Assistant\Tools\ToolContext;
use App\Modules\Assistant\Tools\ToolResult;
use App\Modules\Identity\Guest\GuestContext;
use App\Modules\Requests\Enums\RequestStatus;
use App\Modules\Requests\Enums\RequestType;
use App\Modules\Requests\Presenters\RequestCards;
use App\Modules\Requests\Support\RequestAccess;
use Illuminate\Validation\Rule;

/**
 * The guest's own requests (this session plus requests opened with
 * reference + contact), 20 per page. Contact data and internal notes are
 * never part of the result.
 */
class ListMyRequestsTool extends BaseTool
{
    public const PER_PAGE = 20;

    public function name(): string
    {
        return 'list_my_requests';
    }

    protected function description(): string
    {
        return 'List the guest\'s own reservations, orders, and other requests with their current status. Optionally filter by type or status.';
    }

    protected function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'type' => ['type' => 'string', 'enum' => RequestType::values()],
                'status' => ['type' => 'string', 'enum' => RequestStatus::values()],
                'page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
            ],
        ];
    }

    public function validate(GuestContext $guest, array $arguments): array
    {
        return ToolArguments::validate($arguments, [
            'type' => ['sometimes', 'nullable', 'string', Rule::in(RequestType::values())],
            'status' => ['sometimes', 'nullable', 'string', Rule::in(RequestStatus::values())],
            'page' => ['sometimes', 'integer:strict', 'min:1', 'max:100'],
        ]);
    }

    public function execute(GuestContext $guest, array $arguments, ToolContext $context): ToolResult
    {
        $page = RequestAccess::visibleToGuest($guest)
            ->when($arguments['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($arguments['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE, ['*'], 'page', (int) ($arguments['page'] ?? 1));

        $items = $page->getCollection();

        return new ToolResult(
            ToolResult::OK,
            [
                'total' => $page->total(),
                'page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'requests' => $items->map(fn (GuestRequest $request) => [
                    'reference' => $request->reference,
                    'type' => $request->type->value,
                    'status' => $request->status->value,
                ])->all(),
            ],
            ui: $items->isEmpty() ? [] : [RequestCards::list($items, $page->total())],
            reference: ['total' => $page->total()],
            terminal: true,
            text: $items->isEmpty()
                ? __('deck.assistant.tools.requests_none')
                : __('deck.assistant.tools.requests_found', ['count' => $page->total()]),
        );
    }

    public function loggableInput(array $arguments): array
    {
        return [
            'type' => $arguments['type'] ?? null,
            'status' => $arguments['status'] ?? null,
            'page' => $arguments['page'] ?? 1,
        ];
    }
}
