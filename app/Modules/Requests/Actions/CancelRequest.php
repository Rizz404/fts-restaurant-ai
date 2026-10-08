<?php

namespace App\Modules\Requests\Actions;

use App\Models\GuestRequest;
use App\Models\OperationReceipt;
use App\Models\RequestEvent;
use App\Models\RequestMessage;
use App\Modules\Conversation\Support\Fingerprint;
use App\Modules\Identity\Guest\GuestContext;
use App\Modules\Requests\Actions\Concerns\RunsIdempotently;
use App\Modules\Requests\Enums\MessageVisibility;
use App\Modules\Requests\Enums\RequestStatus;
use App\Modules\Requests\Support\Receipts;
use App\Modules\Requests\Support\RequestAccess;
use App\Modules\Requests\Support\RequestNotifier;
use App\Modules\Requests\Support\SeatLedger;
use App\Support\Http\ApiException;

/**
 * The guest (owner session or a session that opened the request with
 * reference + contact) cancels a pending or confirmed request. Releases held
 * seats, records the optional reason as a public message, and notifies staff.
 */
class CancelRequest
{
    use RunsIdempotently;

    /**
     * @return array{result: GuestRequest, replayed: bool}
     */
    public function handle(GuestContext $guest, GuestRequest $request, ?string $reason, string $key): array
    {
        $reason = $reason === null ? null : trim($reason);
        $fingerprint = Fingerprint::of(['actor' => $guest->guestSessionId, 'request' => $request->public_id, 'reason' => $reason]);

        return $this->idempotent(OperationReceipt::ACTOR_GUEST, $guest->guestSessionId, Receipts::CANCEL, $key, $fingerprint, function () use ($guest, $request, $reason) {
            /** @var GuestRequest $request */
            $request = GuestRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if (! RequestAccess::guestMayAct($guest, $request)) {
                abort(404);
            }

            if ($request->isTerminal()) {
                throw new ApiException('request_closed', 409);
            }

            $from = $request->status;
            $request->forceFill([
                'status' => RequestStatus::Cancelled,
                'version' => $request->version + 1,
                'terminal_at' => now(),
            ])->save();

            SeatLedger::release($request);

            if ($reason !== null && $reason !== '') {
                RequestMessage::create([
                    'request_id' => $request->id,
                    'author_type' => RequestMessage::AUTHOR_GUEST,
                    'visibility' => MessageVisibility::Public,
                    'text' => $reason,
                    'created_at' => now(),
                ]);
            }

            RequestEvent::create([
                'request_id' => $request->id,
                'actor_type' => RequestEvent::ACTOR_GUEST,
                'event_code' => 'cancelled',
                'previous_status' => $from->value,
                'next_status' => RequestStatus::Cancelled->value,
                'created_at' => now(),
            ]);

            RequestNotifier::guestCancelled($request);

            return [$request, 'request', $request->public_id];
        }, fn ($receipt) => GuestRequest::query()->where('public_id', $receipt->resource_public_id)->firstOrFail());
    }
}
