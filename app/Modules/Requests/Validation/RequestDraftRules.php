<?php

namespace App\Modules\Requests\Validation;

use App\Modules\Requests\Enums\RequestType;
use App\Modules\Requests\Support\OpeningSchedule;
use App\Modules\Requests\Support\OrderPricing;
use App\Modules\Requests\Support\RequestReference;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Domain validation of a request draft, shared by the forms, the API, and the
 * AI tools (the AI never gets looser rules). Payload shapes follow
 * TECHNICAL_CONTRACTS "Request dan perubahan status":
 *
 * - reservation: date, slot, party_size, notes, seating_preference
 * - order: fulfilment, requested_time, items[], notes
 * - feedback: kind, subject, body, related_reference
 * - special_request: subject, body, date
 *
 * Contact (name, phone, email) is validated for format only here; whether it
 * is required is decided at confirmation (RequestType::requiresContact()).
 * Errors are keyed payload.* and contact.*.
 */
final class RequestDraftRules
{
    public const PHONE_PATTERN = '/\A\+?[0-9][0-9 ()\-]{4,30}\z/';

    public const MAX_ITEMS = 20;

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $contact  name, phone, email (all optional)
     * @return array{type: RequestType, payload: array<string, mixed>, contact: array{name: ?string, phone: ?string, email: ?string}}
     *
     * @throws ValidationException
     */
    public static function validate(RequestType|string $type, array $payload, array $contact = []): array
    {
        $type = $type instanceof RequestType ? $type : (RequestType::tryFrom($type) ?? throw ValidationException::withMessages(['type' => __('requests.errors.unknown_type')]));

        $rules = self::payloadRules($type) + self::contactRules();
        $validator = Validator::make(['payload' => $payload, 'contact' => $contact], $rules);

        $validator->after(function ($validator) use ($type, $payload) {
            foreach (array_diff(array_keys($payload), self::allowedKeys($type)) as $unknown) {
                $validator->errors()->add('payload.'.mb_substr((string) $unknown, 0, 40), __('requests.errors.unknown_field'));
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            foreach (self::domainErrors($type, $validator->getData()['payload']) as $field => $message) {
                $validator->errors()->add('payload.'.$field, $message);
            }
        });

        $validated = $validator->validate();

        return [
            'type' => $type,
            'payload' => self::normalizePayload($type, $validated['payload']),
            'contact' => self::normalizeContact($validated['contact'] ?? []),
        ];
    }

    /**
     * @return list<string>
     */
    public static function allowedKeys(RequestType $type): array
    {
        return match ($type) {
            RequestType::Reservation => ['date', 'slot', 'party_size', 'notes', 'seating_preference'],
            RequestType::Order => ['fulfilment', 'requested_time', 'items', 'notes'],
            RequestType::Feedback => ['kind', 'subject', 'body', 'related_reference'],
            RequestType::SpecialRequest => ['subject', 'body', 'date'],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function payloadRules(RequestType $type): array
    {
        $maxParty = app(OpeningSchedule::class)->setting()->maxPartySize();

        return match ($type) {
            RequestType::Reservation => [
                'payload.date' => ['required', 'date_format:Y-m-d'],
                'payload.slot' => ['required', 'date_format:H:i'],
                'payload.party_size' => ['required', 'integer', 'min:1', 'max:'.$maxParty],
                'payload.notes' => ['nullable', 'string', 'max:500'],
                'payload.seating_preference' => ['nullable', Rule::in(['indoor', 'outdoor', 'any'])],
            ],
            RequestType::Order => [
                'payload.fulfilment' => ['required', Rule::in(['pickup', 'dine_in_preorder'])],
                'payload.requested_time' => ['required', 'date_format:H:i'],
                'payload.items' => ['required', 'array', 'min:1', 'max:'.self::MAX_ITEMS],
                'payload.items.*' => ['array:menu_item_id,quantity,note'],
                'payload.items.*.menu_item_id' => ['required', 'integer', 'min:1'],
                'payload.items.*.quantity' => ['required', 'integer', 'min:1', 'max:20'],
                'payload.items.*.note' => ['nullable', 'string', 'max:200'],
                'payload.notes' => ['nullable', 'string', 'max:500'],
            ],
            RequestType::Feedback => [
                'payload.kind' => ['required', Rule::in(['compliment', 'complaint', 'suggestion'])],
                'payload.subject' => ['required', 'string', 'min:1', 'max:160'],
                'payload.body' => ['required', 'string', 'min:1', 'max:5000'],
                'payload.related_reference' => ['nullable', 'string', 'regex:'.RequestReference::PATTERN],
            ],
            RequestType::SpecialRequest => [
                'payload.subject' => ['required', 'string', 'min:1', 'max:160'],
                'payload.body' => ['required', 'string', 'min:1', 'max:2000'],
                'payload.date' => ['nullable', 'date_format:Y-m-d'],
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function contactRules(): array
    {
        return [
            'contact' => ['array'],
            'contact.name' => ['nullable', 'string', 'min:1', 'max:80'],
            'contact.phone' => ['nullable', 'string', 'max:32', 'regex:'.self::PHONE_PATTERN],
            'contact.email' => ['nullable', 'string', 'email', 'max:255'],
        ];
    }

    /**
     * Rules that depend on the restaurant's hours, closed dates, and menu.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, string> field (without the payload. prefix) => message
     */
    private static function domainErrors(RequestType $type, array $payload): array
    {
        $schedule = app(OpeningSchedule::class);

        if ($type === RequestType::Reservation) {
            $date = (string) $payload['date'];
            $slot = (string) $payload['slot'];

            if (! $schedule->isWithinWindow($date)) {
                return ['date' => __('requests.errors.date_window', ['days' => $schedule->setting()->maxAdvanceDays()])];
            }
            if ($schedule->isClosedDate($date) || $schedule->rangesFor($date) === []) {
                return ['date' => __('requests.errors.date_closed')];
            }
            if (! $schedule->isValidSlot($date, $slot)) {
                return ['slot' => __('requests.errors.slot_invalid')];
            }
            if ($schedule->startsAt($date, $slot)->lessThan($schedule->earliestStart())) {
                return ['slot' => __('requests.errors.slot_too_soon', ['minutes' => $schedule->setting()->minLeadMinutes()])];
            }

            return [];
        }

        if ($type === RequestType::Order) {
            $today = $schedule->today();
            $time = (string) $payload['requested_time'];
            $errors = [];

            if ($schedule->isClosedDate($today) || ! $schedule->isWithinHours($today, $time)) {
                $errors['requested_time'] = __('requests.errors.time_outside_hours');
            } elseif ($schedule->startsAt($today, $time)->lessThan($schedule->earliestStart())) {
                $errors['requested_time'] = __('requests.errors.slot_too_soon', ['minutes' => $schedule->setting()->minLeadMinutes()]);
            }

            $priced = OrderPricing::price($payload['items']);
            if ($priced['unavailable'] !== []) {
                $errors['items'] = __('requests.errors.item_unavailable');
            }

            return $errors;
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $payload  Validated.
     * @return array<string, mixed>
     */
    private static function normalizePayload(RequestType $type, array $payload): array
    {
        $string = fn (mixed $value) => trim(str_replace("\r\n", "\n", (string) $value));
        $optional = fn (mixed $value) => ($value === null || trim((string) $value) === '') ? null : $string($value);

        return match ($type) {
            RequestType::Reservation => [
                'date' => (string) $payload['date'],
                'slot' => (string) $payload['slot'],
                'party_size' => (int) $payload['party_size'],
                'notes' => $optional($payload['notes'] ?? null),
                'seating_preference' => $optional($payload['seating_preference'] ?? null) ?? 'any',
            ],
            RequestType::Order => [
                'fulfilment' => (string) $payload['fulfilment'],
                'requested_time' => (string) $payload['requested_time'],
                'items' => array_values(array_map(fn (array $item) => [
                    'menu_item_id' => (int) $item['menu_item_id'],
                    'quantity' => (int) $item['quantity'],
                    'note' => $optional($item['note'] ?? null),
                ], $payload['items'])),
                'notes' => $optional($payload['notes'] ?? null),
            ],
            RequestType::Feedback => [
                'kind' => (string) $payload['kind'],
                'subject' => $string($payload['subject']),
                'body' => $string($payload['body']),
                'related_reference' => $optional($payload['related_reference'] ?? null),
            ],
            RequestType::SpecialRequest => [
                'subject' => $string($payload['subject']),
                'body' => $string($payload['body']),
                'date' => $optional($payload['date'] ?? null),
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $contact
     * @return array{name: ?string, phone: ?string, email: ?string}
     */
    public static function normalizeContact(array $contact): array
    {
        $clean = fn (mixed $value) => ($value === null || trim((string) $value) === '') ? null : trim((string) $value);
        $email = $clean($contact['email'] ?? null);

        return [
            'name' => $clean($contact['name'] ?? null),
            'phone' => $clean($contact['phone'] ?? null),
            'email' => $email === null ? null : mb_strtolower($email),
        ];
    }

    /**
     * Digits-only phone used for lookups and indexing.
     */
    public static function phoneDigits(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        return $digits === '' ? null : $digits;
    }

    /**
     * Required contact fields for a type that are still missing.
     *
     * @param  array{name: ?string, phone: ?string, email: ?string}  $contact
     * @return list<string>
     */
    public static function missingContact(RequestType $type, array $contact): array
    {
        if (! $type->requiresContact()) {
            return [];
        }

        $missing = [];
        if ($contact['name'] === null) {
            $missing[] = 'name';
        }
        if ($contact['phone'] === null || strlen((string) self::phoneDigits($contact['phone'])) < 6) {
            $missing[] = 'phone';
        }

        return $missing;
    }
}
