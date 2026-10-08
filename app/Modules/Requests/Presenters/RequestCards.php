<?php

namespace App\Modules\Requests\Presenters;

use App\Models\AiTurn;
use App\Models\GuestRequest;
use App\Models\RequestDraft;
use App\Models\RequestMessage;
use App\Modules\Identity\Guest\SessionGrants;
use Illuminate\Database\Eloquent\Builder;

/**
 * Server-built UI descriptors for drafts and requests in the chat (types
 * request_draft, request_list, request_detail, request_confirmation,
 * availability_slots). Descriptors are refreshed from the database when an
 * old message is shown, so a confirmed draft turns into its request card
 * and statuses are current. Contact data never appears in a card.
 */
final class RequestCards
{
    /**
     * @return array<string, mixed>
     */
    public static function draft(RequestDraft $draft): array
    {
        $view = RequestPresenter::draft($draft);

        return [
            'type' => 'request_draft',
            'draft_id' => $view['draft_id'],
            'revision' => $view['revision'],
            'type_label' => $view['type_label'],
            'team_label' => $view['team_label'],
            'summary' => $view['summary'],
            'total_formatted' => $view['total_formatted'],
            'expires_at' => $view['expires_at'],
            'expired' => $view['expired'],
            'path' => $view['path'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function reviewAction(RequestDraft $draft): array
    {
        return [
            'type' => 'review_request_draft',
            'label' => __('deck.chat.cards.review_draft'),
            'path' => RequestPresenter::draftPath($draft),
        ];
    }

    /**
     * Reference to the draft or request a turn created (never executable).
     *
     * @return array<string, mixed>
     */
    public static function operation(RequestDraft $draft): array
    {
        $draft->loadMissing('consumedRequest');

        if ($draft->consumedRequest !== null) {
            return [
                'type' => 'request',
                'request_id' => $draft->consumedRequest->public_id,
                'reference' => $draft->consumedRequest->reference,
                'path' => RequestPresenter::requestPath($draft->consumedRequest),
            ];
        }

        return ['type' => 'draft', 'draft_id' => $draft->public_id, 'revision' => $draft->revision, 'path' => RequestPresenter::draftPath($draft)];
    }

    /**
     * @return array<string, mixed>
     */
    public static function confirmation(GuestRequest $request): array
    {
        return ['type' => 'request_confirmation'] + self::summaryFields($request);
    }

    /**
     * @param  iterable<GuestRequest>  $requests
     * @return array<string, mixed>
     */
    public static function list(iterable $requests, int $total): array
    {
        $items = [];
        foreach ($requests as $request) {
            $items[] = self::summaryFields($request);
        }

        return ['type' => 'request_list', 'items' => $items, 'total' => $total];
    }

    /**
     * Detail card with the last public messages (guest view only).
     *
     * @param  iterable<RequestMessage>  $messages
     * @return array<string, mixed>
     */
    public static function detail(GuestRequest $request, iterable $messages): array
    {
        $rows = [];
        foreach ($messages as $message) {
            $rows[] = RequestPresenter::message($message, true);
        }

        return ['type' => 'request_detail'] + self::summaryFields($request) + [
            'team_label' => $request->type->team()->label(),
            'assignee_name' => $request->assignee?->name,
            'messages' => $rows,
        ];
    }

    /**
     * @param  array{date: string, party_size: int, slots: list<array{slot: string, available_seats: int, can_book: bool}>}  $availability
     * @return array<string, mixed>
     */
    public static function availability(array $availability): array
    {
        return [
            'type' => 'availability_slots',
            'date' => $availability['date'],
            'date_label' => RequestPresenter::dateLabel($availability['date']),
            'party_size' => $availability['party_size'],
            'slots' => $availability['slots'],
            'path' => route('experience.reservation', ['date' => $availability['date'], 'party' => $availability['party_size']], absolute: false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function summaryFields(GuestRequest $request): array
    {
        return [
            'request_id' => $request->public_id,
            'reference' => $request->reference,
            'type_label' => $request->type->label(),
            'summary' => RequestPresenter::summary($request->type, $request->payload, $request->pricing),
            'status' => $request->status->value,
            'status_label' => $request->status->label(),
            'status_tone' => $request->status->tone(),
            'updated_at' => $request->updated_at?->toIso8601String(),
            'path' => RequestPresenter::requestPath($request),
        ];
    }

    /**
     * Cards and operation of the draft this turn saved (used when the turn
     * failed after saving it, so the guest can still review it).
     *
     * @return array{sources: list<array<string, mixed>>, ui: list<array<string, mixed>>, actions: list<array<string, mixed>>, operation: array<string, mixed>}|null
     */
    public static function draftCardsForTurn(AiTurn $turn): ?array
    {
        $draft = RequestDraft::query()->where('originating_turn_id', $turn->id)->first();
        if ($draft === null) {
            return null;
        }

        return [
            'sources' => [],
            'ui' => [self::draft($draft)],
            'actions' => [self::reviewAction($draft)],
            'operation' => self::operation($draft),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function operationForTurn(AiTurn $turn): ?array
    {
        $draft = RequestDraft::query()
            ->where('originating_turn_id', $turn->id)
            ->where('guest_session_id', $turn->conversation->guest_session_id)
            ->first();

        return $draft === null ? null : self::operation($draft);
    }

    /**
     * Re-reads drafts and requests referenced by stored cards. Cards of
     * drafts/requests that no longer exist (or belong to someone else) are
     * dropped; a consumed draft becomes its request confirmation card.
     *
     * @param  array<string, mixed>  $ui
     * @return array<string, mixed>
     */
    public static function refresh(array $ui, int $guestSessionId): array
    {
        $cards = $ui['ui'] ?? [];
        if (! is_array($cards) || $cards === []) {
            return $ui;
        }

        $draftIds = [];
        $requestIds = [];
        foreach ($cards as $card) {
            $type = $card['type'] ?? null;
            if ($type === 'request_draft') {
                $draftIds[] = (string) ($card['draft_id'] ?? '');
            } elseif (in_array($type, ['request_detail', 'request_confirmation'], true)) {
                $requestIds[] = (string) ($card['request_id'] ?? '');
            } elseif ($type === 'request_list') {
                foreach ((array) ($card['items'] ?? []) as $item) {
                    $requestIds[] = (string) ($item['request_id'] ?? '');
                }
            }
        }

        $drafts = $draftIds === [] ? collect() : RequestDraft::query()
            ->with('consumedRequest')
            ->whereIn('public_id', array_filter($draftIds))
            ->where('guest_session_id', $guestSessionId)
            ->get()
            ->keyBy('public_id');

        $grants = app(SessionGrants::class)->all();
        $requests = $requestIds === [] ? collect() : GuestRequest::query()
            ->with(['assignee:id,name'])
            ->whereIn('public_id', array_filter($requestIds))
            ->where(fn (Builder $query) => $query->where('guest_session_id', $guestSessionId)->when($grants !== [], fn (Builder $q) => $q->orWhereIn('public_id', $grants)))
            ->get()
            ->keyBy('public_id');

        $result = [];
        foreach ($cards as $card) {
            $type = $card['type'] ?? null;

            if ($type === 'request_draft') {
                $draft = $drafts->get((string) ($card['draft_id'] ?? ''));
                if ($draft === null) {
                    continue;
                }
                $result[] = $draft->consumedRequest !== null ? self::confirmation($draft->consumedRequest) : self::draft($draft);
            } elseif ($type === 'request_confirmation') {
                $request = $requests->get((string) ($card['request_id'] ?? ''));
                if ($request !== null) {
                    $result[] = self::confirmation($request);
                }
            } elseif ($type === 'request_detail') {
                $request = $requests->get((string) ($card['request_id'] ?? ''));
                if ($request !== null) {
                    $result[] = ['type' => 'request_detail'] + self::summaryFields($request) + [
                        'team_label' => $request->type->team()->label(),
                        'assignee_name' => $request->assignee?->name,
                        'messages' => $card['messages'] ?? [],
                    ];
                }
            } elseif ($type === 'request_list') {
                $items = [];
                foreach ((array) ($card['items'] ?? []) as $item) {
                    $request = $requests->get((string) ($item['request_id'] ?? ''));
                    if ($request !== null) {
                        $items[] = self::summaryFields($request);
                    }
                }
                if ($items !== []) {
                    $result[] = ['type' => 'request_list', 'items' => $items, 'total' => count($items)];
                }
            } else {
                $result[] = $card;
            }
        }

        $ui['ui'] = $result;

        // A review button only makes sense while a draft card is still shown.
        $hasDraftCard = collect($result)->contains(fn ($card) => ($card['type'] ?? null) === 'request_draft');
        if (! $hasDraftCard && isset($ui['actions']) && is_array($ui['actions'])) {
            $ui['actions'] = array_values(array_filter($ui['actions'], fn ($action) => ($action['type'] ?? null) !== 'review_request_draft'));
        }

        return $ui;
    }
}
