<?php

namespace App\Modules\Requests\Presenters;

use App\Models\GuestRequest;
use App\Models\RequestDraft;
use App\Models\RequestMessage;
use App\Modules\Menu\Support\Money;
use App\Modules\Requests\Enums\MessageVisibility;
use App\Modules\Requests\Enums\RequestType;
use App\Modules\Requests\Support\OrderPricing;
use Carbon\CarbonImmutable;

/**
 * Shapes requests, drafts, and messages for pages and the API. Guest shapes
 * never include internal notes, assignee data, or other people's contact
 * details; staff shapes add contact, assignee, and team.
 */
final class RequestPresenter
{
    /**
     * One-line description of what was requested, in the active locale.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>|null  $pricing
     */
    public static function summary(RequestType $type, array $payload, ?array $pricing = null): string
    {
        return match ($type) {
            RequestType::Reservation => self::dateLabel((string) $payload['date']).' '.$payload['slot'].' · '.__('requests.party_size_label', ['count' => $payload['party_size']]),
            RequestType::Order => self::orderSummary($payload, $pricing),
            RequestType::Feedback => __('requests.feedback_kinds.'.($payload['kind'] ?? 'suggestion')).': '.($payload['subject'] ?? ''),
            RequestType::SpecialRequest => (string) ($payload['subject'] ?? ''),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>|null  $pricing
     */
    private static function orderSummary(array $payload, ?array $pricing): string
    {
        $pricing ??= OrderPricing::price($payload['items'] ?? []);
        $lines = collect($pricing['lines'] ?? [])->map(fn (array $line) => $line['quantity'].'× '.$line['name'])->implode(', ');
        $parts = [__('requests.fulfilment.'.($payload['fulfilment'] ?? 'pickup')).' '.($payload['requested_time'] ?? ''), $lines];

        if (($pricing['total'] ?? 0) > 0) {
            $parts[] = __('requests.total').' '.Money::format((int) $pricing['total'], (string) ($pricing['currency'] ?? config('restaurant.currency')));
        }

        return implode(' · ', array_filter($parts));
    }

    public static function dateLabel(string $date): string
    {
        $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $date, config('restaurant.timezone'));

        return $parsed === false ? $date : $parsed->locale(app()->getLocale())->isoFormat('ddd, D MMM YYYY');
    }

    /**
     * Formatted order total for a draft (live prices), or null for other types.
     */
    public static function draftTotal(RequestDraft $draft): ?string
    {
        if ($draft->type !== RequestType::Order) {
            return null;
        }

        $priced = OrderPricing::price($draft->payload['items'] ?? []);

        return $priced['total'] > 0 ? Money::format($priced['total'], $priced['currency']) : null;
    }

    /**
     * Review page path of a draft: the slide that holds its form.
     */
    public static function draftPath(RequestDraft $draft): string
    {
        $route = match ($draft->type) {
            RequestType::Reservation => 'experience.reservation',
            RequestType::Order => 'experience.order',
            default => 'experience.bookings',
        };

        return route($route, ['draft' => $draft->public_id], absolute: false);
    }

    public static function requestPath(GuestRequest $request): string
    {
        return route('experience.bookings.show', $request->reference, absolute: false);
    }

    /**
     * @return array<string, mixed>
     */
    public static function draft(RequestDraft $draft): array
    {
        $draft->loadMissing('team:id,code');

        return [
            'draft_id' => $draft->public_id,
            'type' => $draft->type->value,
            'type_label' => $draft->type->label(),
            'team_label' => $draft->type->team()->label(),
            'payload' => $draft->payload,
            'contact' => ['name' => $draft->contact_name, 'phone' => $draft->contact_phone, 'email' => $draft->contact_email],
            'revision' => $draft->revision,
            'summary' => self::summary($draft->type, $draft->payload),
            'total_formatted' => self::draftTotal($draft),
            'expires_at' => $draft->expires_at->toIso8601String(),
            'expired' => $draft->isExpired(),
            'consumed' => $draft->isConsumed(),
            'path' => self::draftPath($draft),
        ];
    }

    /**
     * What a guest may see of a request.
     *
     * @return array<string, mixed>
     */
    public static function forGuest(GuestRequest $request): array
    {
        return [
            'request_id' => $request->public_id,
            'reference' => $request->reference,
            'type' => $request->type->value,
            'type_label' => $request->type->label(),
            'status' => $request->status->value,
            'status_label' => $request->status->label(),
            'status_tone' => $request->status->tone(),
            'version' => $request->version,
            'summary' => self::summary($request->type, $request->payload, $request->pricing),
            'payload' => $request->payload,
            'pricing' => $request->pricing === null ? null : $request->pricing + ['total_formatted' => Money::format((int) $request->pricing['total'], (string) $request->pricing['currency'])],
            'reserved_date' => $request->reserved_date?->format('Y-m-d'),
            'reserved_slot' => $request->reserved_slot,
            'party_size' => $request->party_size,
            'can_cancel' => ! $request->isTerminal(),
            'created_at' => $request->confirmed_at->toIso8601String(),
            'updated_at' => $request->updated_at?->toIso8601String(),
            'path' => self::requestPath($request),
        ];
    }

    /**
     * Staff view: the guest view plus contact, team, assignee, and table.
     *
     * @return array<string, mixed>
     */
    public static function forStaff(GuestRequest $request): array
    {
        $request->loadMissing(['assignee:id,name', 'team:id,code']);

        return self::forGuest($request) + [
            'contact' => ['name' => $request->contact_name, 'phone' => $request->contact_phone, 'email' => $request->contact_email],
            'table_label' => $request->table_label,
            'team_label' => $request->type->team()->label(),
            'assignee' => $request->assignee === null ? null : ['id' => $request->assignee->id, 'name' => $request->assignee->name],
            'terminal_at' => $request->terminal_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function message(RequestMessage $message, bool $forGuest): array
    {
        $isStaff = $message->author_type === RequestMessage::AUTHOR_STAFF;

        return [
            'id' => $message->id,
            'author_type' => $message->author_type,
            'author_name' => $isStaff ? ($message->author?->name ?? __('requests.staff')) : __('requests.you'),
            'mine' => $forGuest ? ! $isStaff : $isStaff,
            'visibility' => $forGuest ? MessageVisibility::Public->value : $message->visibility->value,
            'text' => $message->text,
            'created_at' => $message->created_at->toIso8601String(),
        ];
    }
}
