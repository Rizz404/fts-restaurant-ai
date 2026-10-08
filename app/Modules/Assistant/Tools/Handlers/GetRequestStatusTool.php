<?php

namespace App\Modules\Assistant\Tools\Handlers;

use App\Models\GuestRequest;
use App\Models\RequestMessage;
use App\Modules\Assistant\Tools\ToolArguments;
use App\Modules\Assistant\Tools\ToolContext;
use App\Modules\Assistant\Tools\ToolResult;
use App\Modules\Identity\Guest\GuestContext;
use App\Modules\Requests\Enums\MessageVisibility;
use App\Modules\Requests\Presenters\RequestCards;
use App\Modules\Requests\Support\RequestAccess;
use App\Modules\Requests\Support\RequestReference;

/**
 * Status of one request by reference. Only a request this guest session
 * owns or opened with reference + contact is returned; anything else is
 * "not found" without saying whether the reference exists. This tool never
 * looks a request up by contact details (only the /bookings form does).
 */
class GetRequestStatusTool extends BaseTool
{
    public function name(): string
    {
        return 'get_request_status';
    }

    protected function description(): string
    {
        return 'Get the status and latest staff replies of one of the guest\'s requests by its reference (for example RST-20261007-ABCD2345). Only the guest\'s own requests are available.';
    }

    protected function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'reference' => ['type' => 'string', 'maxLength' => 40, 'description' => 'The request reference starting with RST-.'],
            ],
            'required' => ['reference'],
        ];
    }

    public function validate(GuestContext $guest, array $arguments): array
    {
        $validated = ToolArguments::validate($arguments, [
            'reference' => ['required', 'string', 'max:40'],
        ]);

        $validated['reference'] = RequestReference::normalize($validated['reference']);

        return $validated;
    }

    public function execute(GuestContext $guest, array $arguments, ToolContext $context): ToolResult
    {
        $reference = $arguments['reference'];
        $request = RequestReference::isValid($reference)
            ? GuestRequest::query()->with('assignee:id,name')->where('reference', $reference)->first()
            : null;

        if ($request === null || ! RequestAccess::guestMayAct($guest, $request)) {
            return new ToolResult(
                ToolResult::NOT_FOUND,
                ['error' => 'request_not_found'],
                reference: ['found' => false],
                terminal: true,
                text: __('deck.assistant.tools.request_not_found'),
            );
        }

        $messages = RequestMessage::query()
            ->with('author:id,name')
            ->where('request_id', $request->id)
            ->where('visibility', MessageVisibility::Public->value)
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->reverse()
            ->values();

        return new ToolResult(
            ToolResult::OK,
            ['reference' => $request->reference, 'status' => $request->status->value],
            ui: [RequestCards::detail($request, $messages)],
            actions: [[
                'type' => 'open_request',
                'label' => __('deck.chat.cards.open_request', ['reference' => $request->reference]),
                'path' => route('experience.bookings.show', $request->reference, absolute: false),
            ]],
            reference: ['request_id' => $request->public_id],
            terminal: true,
            text: __('deck.assistant.tools.request_status', ['reference' => $request->reference, 'status' => $request->status->label()]),
        );
    }

    public function loggableInput(array $arguments): array
    {
        return ['reference' => $arguments['reference'] ?? null];
    }
}
