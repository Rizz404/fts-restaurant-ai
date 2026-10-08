<?php

namespace App\Modules\Assistant\Tools\Handlers;

use App\Modules\Assistant\Tools\ToolArguments;
use App\Modules\Assistant\Tools\ToolContext;
use App\Modules\Assistant\Tools\ToolResult;
use App\Modules\Experience\Support\RestaurantSettings;
use App\Modules\Identity\Guest\GuestContext;
use App\Modules\Requests\Actions\CheckAvailability;
use App\Modules\Requests\Presenters\RequestCards;
use App\Modules\Requests\Presenters\RequestPresenter;

/**
 * Read-only reservation slots for a date and party size. The reply and the
 * slot card are written by the server from the same data as the reservation
 * form; nothing is held or booked by this tool.
 */
class CheckAvailabilityTool extends BaseTool
{
    public function __construct(
        private readonly CheckAvailability $availability,
        private readonly RestaurantSettings $settings,
    ) {}

    public function name(): string
    {
        return 'check_availability';
    }

    protected function description(): string
    {
        return 'Check which reservation time slots are open on a date for a party size. Read-only. Ask the guest for the date and number of guests first if they did not say them.';
    }

    protected function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'date' => ['type' => 'string', 'description' => 'Date as YYYY-MM-DD.', 'pattern' => '^\d{4}-\d{2}-\d{2}$'],
                'party_size' => ['type' => 'integer', 'minimum' => 1, 'maximum' => $this->maxParty()],
            ],
            'required' => ['date', 'party_size'],
        ];
    }

    public function validate(GuestContext $guest, array $arguments): array
    {
        return ToolArguments::validate($arguments, [
            'date' => ['required', 'string', 'date_format:Y-m-d'],
            'party_size' => ['required', 'integer:strict', 'min:1', 'max:'.$this->maxParty()],
        ]);
    }

    public function execute(GuestContext $guest, array $arguments, ToolContext $context): ToolResult
    {
        // Domain errors (closed date, outside the booking window) arrive as ApiException and become tool errors.
        $result = $this->availability->handle($arguments['date'], (int) $arguments['party_size']);

        $open = array_values(array_filter($result['slots'], fn (array $slot) => $slot['can_book']));
        $card = RequestCards::availability($result);
        $text = $open === []
            ? __('deck.assistant.tools.slots_none', ['date' => RequestPresenter::dateLabel($result['date']), 'party' => $result['party_size']])
            : __('deck.assistant.tools.slots_found', ['date' => RequestPresenter::dateLabel($result['date']), 'party' => $result['party_size']]);

        return new ToolResult(
            ToolResult::OK,
            [
                'date' => $result['date'],
                'party_size' => $result['party_size'],
                'closed' => $result['closed'],
                'open_slots' => array_map(fn (array $slot) => $slot['slot'], $open),
            ],
            ui: [$card],
            actions: [[
                'type' => 'open_scene',
                'scene' => 'reservation',
                'label' => __('deck.chat.cards.open_scene', ['slide' => __('deck.slides.reservation.label')]),
                'path' => $card['path'],
            ]],
            reference: ['date' => $result['date'], 'open' => count($open)],
            terminal: true,
            text: $text,
        );
    }

    public function loggableInput(array $arguments): array
    {
        return ['date' => $arguments['date'] ?? null, 'party_size' => $arguments['party_size'] ?? null];
    }

    private function maxParty(): int
    {
        return $this->settings->get()->maxPartySize();
    }
}
