<?php

namespace App\Modules\Requests\Actions;

use App\Models\RequestDraft;
use App\Modules\Identity\Guest\GuestContext;
use App\Modules\Requests\Validation\RequestDraftRules;
use App\Support\Http\ApiException;
use Illuminate\Support\Facades\DB;

/**
 * Edits an unconfirmed draft (payload and contact). Requires the expected
 * revision; each edit increments the revision, renews the 15-minute window,
 * and changes the fingerprint. The type cannot change.
 */
class UpdateRequestDraft
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $contact
     */
    public function handle(GuestContext $guest, RequestDraft $draft, int $expectedRevision, array $payload, array $contact = []): RequestDraft
    {
        return DB::transaction(function () use ($guest, $draft, $expectedRevision, $payload, $contact) {
            /** @var RequestDraft $draft */
            $draft = RequestDraft::query()->whereKey($draft->id)->lockForUpdate()->firstOrFail();

            abort_unless($draft->guest_session_id === $guest->guestSessionId, 404);

            if ($draft->isConsumed()) {
                throw new ApiException('draft_consumed', 409);
            }
            if ($draft->revision !== $expectedRevision) {
                throw new ApiException('revision_conflict', 409);
            }
            if ($draft->isExpired()) {
                throw new ApiException('draft_expired', 409);
            }

            $validated = RequestDraftRules::validate($draft->type, $payload, $contact);
            $revision = $draft->revision + 1;

            $draft->forceFill([
                'payload' => $validated['payload'],
                'contact_name' => $validated['contact']['name'],
                'contact_phone' => $validated['contact']['phone'],
                'contact_email' => $validated['contact']['email'],
                'revision' => $revision,
                'payload_fingerprint' => PrepareRequestDraft::fingerprint($guest->guestSessionId, $draft->type, $validated['payload'], $validated['contact'], $revision),
                'expires_at' => now()->addMinutes(PrepareRequestDraft::TTL_MINUTES),
            ])->save();

            return $draft;
        });
    }
}
