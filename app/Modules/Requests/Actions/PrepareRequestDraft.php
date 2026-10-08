<?php

namespace App\Modules\Requests\Actions;

use App\Models\Conversation;
use App\Models\RequestDraft;
use App\Models\StaffTeam;
use App\Modules\Conversation\Support\Fingerprint;
use App\Modules\Identity\Guest\GuestContext;
use App\Modules\Requests\Enums\RequestType;
use App\Modules\Requests\Validation\RequestDraftRules;
use App\Support\Http\ApiException;
use Illuminate\Support\Str;

/**
 * Saves a reviewable draft for the guest session (revision 1, 15 minutes).
 * Never creates a request; ConfirmRequestDraft does that after an explicit
 * confirmation by the guest. The form flow and the AI prepare_* tools both
 * call this, with the same validation.
 */
class PrepareRequestDraft
{
    public const TTL_MINUTES = 15;

    /**
     * @param  array<string, mixed>  $payload  Type-specific fields (see RequestDraftRules).
     * @param  array<string, mixed>  $contact  name, phone, email (optional here, never from the AI).
     */
    public function handle(GuestContext $guest, RequestType|string $type, array $payload, array $contact = [], ?string $conversationPublicId = null, ?int $originatingTurnId = null): RequestDraft
    {
        $validated = RequestDraftRules::validate($type, $payload, $contact);
        $type = $validated['type'];

        $conversationId = null;
        if ($conversationPublicId !== null) {
            $conversation = Conversation::query()->where('public_id', $conversationPublicId)->first();
            if ($conversation === null || $conversation->guest_session_id !== $guest->guestSessionId) {
                throw new ApiException('validation_failed', 422, ['conversation_public_id' => [__('requests.errors.invalid_conversation')]]);
            }
            $conversationId = $conversation->id;
        }

        $team = self::team($type);

        return RequestDraft::create([
            'public_id' => (string) Str::uuid(),
            'guest_session_id' => $guest->guestSessionId,
            'conversation_id' => $conversationId,
            'originating_turn_id' => $originatingTurnId,
            'type' => $type,
            'team_id' => $team->id,
            'payload' => $validated['payload'],
            'contact_name' => $validated['contact']['name'],
            'contact_phone' => $validated['contact']['phone'],
            'contact_email' => $validated['contact']['email'],
            'table_label' => $guest->tableLabel,
            'revision' => 1,
            'payload_fingerprint' => self::fingerprint($guest->guestSessionId, $type, $validated['payload'], $validated['contact'], 1),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ]);
    }

    public static function team(RequestType $type): StaffTeam
    {
        return StaffTeam::query()->active()->where('code', $type->team()->value)->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array{name: ?string, phone: ?string, email: ?string}  $contact
     */
    public static function fingerprint(int $guestSessionId, RequestType $type, array $payload, array $contact, int $revision): string
    {
        return Fingerprint::of([
            'guest_session_id' => $guestSessionId,
            'type' => $type->value,
            'payload' => $payload,
            'contact' => $contact,
            'revision' => $revision,
        ]);
    }
}
