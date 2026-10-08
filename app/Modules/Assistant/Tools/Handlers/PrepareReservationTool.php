<?php

namespace App\Modules\Assistant\Tools\Handlers;

use App\Modules\Requests\Enums\RequestType;

/**
 * Draft of a table reservation. The guest confirms (and adds name and phone)
 * in the review step; capacity is checked again, under a lock, at confirmation.
 */
class PrepareReservationTool extends PrepareDraftTool
{
    public function name(): string
    {
        return 'prepare_reservation';
    }

    protected function description(): string
    {
        return 'Prepare a DRAFT table reservation from what the guest said (date, time, number of guests). It is not sent: the guest reviews it, adds contact details, and confirms with a button. Never invent a date, time, or party size; ask the guest first. Never ask for a name or phone in the chat.';
    }

    protected function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'date' => ['type' => 'string', 'pattern' => '^\d{4}-\d{2}-\d{2}$', 'description' => 'YYYY-MM-DD'],
                'slot' => ['type' => 'string', 'pattern' => '^\d{2}:\d{2}$', 'description' => 'Start time HH:MM, one of the available slots.'],
                'party_size' => ['type' => 'integer', 'minimum' => 1],
                'notes' => ['type' => 'string', 'maxLength' => 500],
                'seating_preference' => ['type' => 'string', 'enum' => ['indoor', 'outdoor', 'any']],
            ],
            'required' => ['date', 'slot', 'party_size'],
        ];
    }

    protected function rules(): array
    {
        return [
            'date' => ['required', 'string', 'date_format:Y-m-d'],
            'slot' => ['required', 'string', 'date_format:H:i'],
            'party_size' => ['required', 'integer:strict', 'min:1'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:500'],
            'seating_preference' => ['sometimes', 'nullable', 'string', 'in:indoor,outdoor,any'],
        ];
    }

    protected function requestType(array $arguments): RequestType
    {
        return RequestType::Reservation;
    }

    protected function payload(array $arguments): array
    {
        return [
            'date' => $arguments['date'],
            'slot' => $arguments['slot'],
            'party_size' => $arguments['party_size'],
            'notes' => $arguments['notes'] ?? null,
            'seating_preference' => $arguments['seating_preference'] ?? 'any',
        ];
    }
}
