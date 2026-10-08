<?php

namespace App\Modules\Requests\Actions;

use App\Models\GuestRequest;
use App\Models\OperationReceipt;
use App\Models\RequestEvent;
use App\Models\RequestMessage;
use App\Models\User;
use App\Modules\Conversation\Support\Fingerprint;
use App\Modules\Identity\ActorContext;
use App\Modules\Requests\Actions\Concerns\RunsIdempotently;
use App\Modules\Requests\Enums\MessageVisibility;
use App\Modules\Requests\Enums\RequestStatus;
use App\Modules\Requests\Enums\RequestType;
use App\Modules\Requests\Support\OrderPricing;
use App\Modules\Requests\Support\Receipts;
use App\Modules\Requests\Support\RequestAccess;
use App\Modules\Requests\Support\SeatLedger;
use App\Support\Http\ApiException;

/**
 * Staff status changes (TECHNICAL_CONTRACTS matrix):
 *   pending → confirmed    staff; orders check dish availability (409 item_unavailable)
 *   pending → declined     staff; reason required (a public message the guest sees)
 *   confirmed → completed  staff
 *   confirmed → no_show    staff; reservations only
 *   pending|confirmed → cancelled   staff; reason required (guests use CancelRequest)
 * Terminal statuses release held seats in the same transaction. Row lock +
 * expected_version; bumps the version.
 */
class TransitionRequest
{
    use RunsIdempotently;

    private const ALLOWED = [
        'pending' => ['confirmed', 'declined', 'cancelled'],
        'confirmed' => ['completed', 'no_show', 'cancelled'],
    ];

    /**
     * @return array{result: GuestRequest, replayed: bool}
     */
    public function handle(User $actor, GuestRequest $request, RequestStatus $target, int $expectedVersion, ?string $reason, string $key): array
    {
        $reason = $reason === null ? null : trim($reason);
        $fingerprint = Fingerprint::of([
            'actor' => $actor->id,
            'request' => $request->public_id,
            'target' => $target->value,
            'version' => $expectedVersion,
            'reason' => $reason,
        ]);

        return $this->idempotent(OperationReceipt::ACTOR_USER, $actor->id, Receipts::TRANSITION, $key, $fingerprint, function () use ($actor, $request, $target, $expectedVersion, $reason) {
            /** @var GuestRequest $request */
            $request = GuestRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if (! RequestAccess::staffCanHandle(ActorContext::load($actor), $request)) {
                abort(404);
            }

            if ($request->version !== $expectedVersion) {
                throw new ApiException('version_conflict', 409);
            }

            $from = $request->status;
            $this->assertAllowed($from, $target, $request, $reason);

            if ($target === RequestStatus::Confirmed && $request->type === RequestType::Order) {
                $this->assertDishesAvailable($request);
            }

            $changes = ['status' => $target, 'version' => $request->version + 1];
            if ($target->isTerminal()) {
                $changes['terminal_at'] = now();
            }
            $request->forceFill($changes)->save();

            if ($target->isTerminal()) {
                SeatLedger::release($request);
            }

            if ($reason !== null && $reason !== '') {
                RequestMessage::create([
                    'request_id' => $request->id,
                    'author_type' => RequestMessage::AUTHOR_STAFF,
                    'author_user_id' => $actor->id,
                    'visibility' => MessageVisibility::Public,
                    'text' => $reason,
                    'created_at' => now(),
                ]);
            }

            RequestEvent::create([
                'request_id' => $request->id,
                'actor_type' => RequestEvent::ACTOR_STAFF,
                'actor_user_id' => $actor->id,
                'event_code' => 'status_changed',
                'previous_status' => $from->value,
                'next_status' => $target->value,
                'created_at' => now(),
            ]);

            return [$request, 'request', $request->public_id];
        }, fn ($receipt) => GuestRequest::query()->where('public_id', $receipt->resource_public_id)->firstOrFail());
    }

    private function assertAllowed(RequestStatus $from, RequestStatus $to, GuestRequest $request, ?string $reason): void
    {
        if (! in_array($to->value, self::ALLOWED[$from->value] ?? [], true)) {
            throw new ApiException('invalid_transition', 422, ['status' => [__('requests.errors.invalid_transition')]]);
        }

        if ($to === RequestStatus::NoShow && $request->type !== RequestType::Reservation) {
            throw new ApiException('invalid_transition', 422, ['status' => [__('requests.errors.invalid_transition')]]);
        }

        if (in_array($to, [RequestStatus::Declined, RequestStatus::Cancelled], true) && ($reason === null || $reason === '')) {
            throw new ApiException('validation_failed', 422, ['reason' => [__('requests.errors.reason_required')]]);
        }
    }

    private function assertDishesAvailable(GuestRequest $request): void
    {
        $priced = OrderPricing::price($request->payload['items'] ?? []);

        if ($priced['unavailable'] !== []) {
            throw new ApiException('item_unavailable', 409, ['items' => array_map(fn (int $id) => (string) $id, $priced['unavailable'])]);
        }
    }
}
