<?php

namespace App\Modules\Requests\Actions;

use App\Models\GuestRequest;
use App\Models\OperationReceipt;
use App\Models\RequestDraft;
use App\Models\RequestEvent;
use App\Models\ReservationSlotUsage;
use App\Modules\Conversation\Support\Fingerprint;
use App\Modules\Identity\Guest\GuestContext;
use App\Modules\Requests\Enums\RequestStatus;
use App\Modules\Requests\Enums\RequestType;
use App\Modules\Requests\Support\OpeningSchedule;
use App\Modules\Requests\Support\OrderPricing;
use App\Modules\Requests\Support\Receipts;
use App\Modules\Requests\Support\RequestNotifier;
use App\Modules\Requests\Support\RequestReference;
use App\Modules\Requests\Validation\RequestDraftRules;
use App\Support\Http\ApiException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turns a draft into exactly one request (PLAN_06 confirmation steps):
 *  1. Receipt for guest session/type/key: same payload replays, a different
 *     payload is a 409.
 *  2. Lock the draft; check owner, expected revision, and expiry.
 *  3. Already consumed with the same revision → the same request, even with a
 *     different key (never a second request).
 *  4. Contact must be complete for the type; the payload is validated again
 *     (the booking window and menu may have moved on).
 *  5. Orders are priced from the database and frozen; sold-out dishes → 409.
 *  6. Reservations: the slot row is locked; booked seats + party size must not
 *     exceed the slot capacity (409 slot_full), then the seats are held.
 *  7. Request, consumed link, receipt, event, seat hold, and staff
 *     notifications are written in one transaction; no model call involved.
 */
class ConfirmRequestDraft
{
    /**
     * @return array{request: GuestRequest, created: bool}
     */
    public function handle(GuestContext $guest, RequestDraft $draft, int $expectedRevision, string $idempotencyKey): array
    {
        $actorType = OperationReceipt::ACTOR_GUEST;
        $fingerprint = Fingerprint::of([
            'actor' => $guest->guestSessionId,
            'draft' => $draft->public_id,
            'revision' => $expectedRevision,
        ]);

        if ($receipt = Receipts::replay($actorType, $guest->guestSessionId, Receipts::CONFIRM_DRAFT, $idempotencyKey, $fingerprint)) {
            return ['request' => GuestRequest::query()->where('public_id', $receipt->resource_public_id)->firstOrFail(), 'created' => false];
        }

        try {
            return DB::transaction(function () use ($guest, $draft, $expectedRevision, $idempotencyKey, $fingerprint, $actorType) {
                /** @var RequestDraft $draft */
                $draft = RequestDraft::query()->whereKey($draft->id)->lockForUpdate()->firstOrFail();

                abort_unless($draft->guest_session_id === $guest->guestSessionId, 404);

                if ($draft->revision !== $expectedRevision) {
                    throw new ApiException('revision_conflict', 409);
                }

                if ($draft->consumed_request_id !== null) {
                    $request = GuestRequest::query()->findOrFail($draft->consumed_request_id);
                    Receipts::record($actorType, $guest->guestSessionId, Receipts::CONFIRM_DRAFT, $idempotencyKey, $fingerprint, 'request', $request->public_id);

                    return ['request' => $request, 'created' => false];
                }

                if ($draft->isExpired()) {
                    throw new ApiException('draft_expired', 409);
                }

                $contact = RequestDraftRules::normalizeContact([
                    'name' => $draft->contact_name,
                    'phone' => $draft->contact_phone,
                    'email' => $draft->contact_email,
                ]);
                $missing = RequestDraftRules::missingContact($draft->type, $contact);
                if ($missing !== []) {
                    throw new ApiException('contact_required', 422, collect($missing)->mapWithKeys(fn (string $field) => ['contact.'.$field => [__('requests.errors.contact_required')]])->all());
                }

                // A dish sold out since the draft was saved is a conflict the guest can fix (409),
                // not a field error: check it before the general validation reports it as one.
                if ($draft->type === RequestType::Order && OrderPricing::price($draft->payload['items'] ?? [])['unavailable'] !== []) {
                    throw new ApiException('item_unavailable', 409, ['payload.items' => [__('requests.errors.item_unavailable')]]);
                }

                // The booking window, hours, and menu may have changed since the draft.
                $validated = RequestDraftRules::validate($draft->type, $draft->payload, $contact);
                $payload = $validated['payload'];

                $pricing = null;
                if ($draft->type === RequestType::Order) {
                    $priced = OrderPricing::price($payload['items']);
                    if ($priced['unavailable'] !== []) {
                        throw new ApiException('item_unavailable', 409, ['payload.items' => [__('requests.errors.item_unavailable')]]);
                    }
                    $pricing = ['lines' => $priced['lines'], 'total' => $priced['total'], 'currency' => $priced['currency']];
                }

                $reservedDate = null;
                $reservedSlot = null;
                $partySize = null;
                if ($draft->type === RequestType::Reservation) {
                    $reservedDate = $payload['date'];
                    $reservedSlot = $payload['slot'];
                    $partySize = $payload['party_size'];
                    $this->holdSeats($reservedDate, $reservedSlot, $partySize);
                }

                $request = GuestRequest::create([
                    'public_id' => (string) Str::uuid(),
                    'reference' => RequestReference::generate(),
                    'type' => $draft->type,
                    'team_id' => $draft->team_id,
                    'status' => RequestStatus::Pending,
                    'version' => 1,
                    'guest_session_id' => $guest->guestSessionId,
                    'contact_name' => $contact['name'],
                    'contact_phone' => $contact['phone'],
                    'contact_phone_digits' => RequestDraftRules::phoneDigits($contact['phone']),
                    'contact_email' => $contact['email'],
                    'table_label' => $draft->table_label,
                    'locale' => app()->getLocale(),
                    'payload' => $payload,
                    'pricing' => $pricing,
                    'reserved_date' => $reservedDate,
                    'reserved_slot' => $reservedSlot,
                    'party_size' => $partySize,
                    'draft_id' => $draft->id,
                    'conversation_id' => $draft->conversation_id,
                    'confirmed_at' => now(),
                ]);

                $draft->forceFill(['consumed_request_id' => $request->id])->save();

                Receipts::record($actorType, $guest->guestSessionId, Receipts::CONFIRM_DRAFT, $idempotencyKey, $fingerprint, 'request', $request->public_id);

                RequestEvent::create([
                    'request_id' => $request->id,
                    'actor_type' => RequestEvent::ACTOR_GUEST,
                    'event_code' => 'created',
                    'next_status' => RequestStatus::Pending->value,
                    'created_at' => now(),
                ]);

                RequestNotifier::created($request);

                return ['request' => $request, 'created' => true];
            });
        } catch (UniqueConstraintViolationException) {
            // A concurrent request with the same key committed first.
            $receipt = Receipts::replay($actorType, $guest->guestSessionId, Receipts::CONFIRM_DRAFT, $idempotencyKey, $fingerprint);
            if ($receipt === null) {
                throw new ApiException('conflict', 409);
            }

            return ['request' => GuestRequest::query()->where('public_id', $receipt->resource_public_id)->firstOrFail(), 'created' => false];
        }
    }

    /**
     * Locks the slot row and holds the seats, or fails with slot_full.
     */
    private function holdSeats(string $date, string $slot, int $partySize): void
    {
        $capacity = app(OpeningSchedule::class)->setting()->slotCapacity();

        // Create the row if it does not exist yet; the lock below serializes competing confirmations.
        DB::table('reservation_slot_usage')->insertOrIgnore([
            'date' => $date,
            'slot_start' => $slot,
            'booked_seats' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /** @var ReservationSlotUsage $usage */
        $usage = ReservationSlotUsage::query()
            ->whereDate('date', $date)
            ->where('slot_start', $slot)
            ->lockForUpdate()
            ->firstOrFail();

        if ($usage->booked_seats + $partySize > $capacity) {
            throw new ApiException('slot_full', 409, ['payload.slot' => [__('requests.errors.slot_full')]]);
        }

        $usage->forceFill(['booked_seats' => $usage->booked_seats + $partySize])->save();
    }
}
