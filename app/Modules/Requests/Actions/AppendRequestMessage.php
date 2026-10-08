<?php

namespace App\Modules\Requests\Actions;

use App\Models\GuestRequest;
use App\Models\OperationReceipt;
use App\Models\RequestMessage;
use App\Models\User;
use App\Modules\Conversation\Support\Fingerprint;
use App\Modules\Identity\ActorContext;
use App\Modules\Identity\Guest\GuestContext;
use App\Modules\Requests\Actions\Concerns\RunsIdempotently;
use App\Modules\Requests\Enums\MessageVisibility;
use App\Modules\Requests\Support\Receipts;
use App\Modules\Requests\Support\RequestAccess;
use App\Modules\Requests\Support\RequestNotifier;
use App\Support\Http\ApiException;

/**
 * Public messages from the guest (owner or granted session) and from the
 * handling team; internal notes from staff only. Locks the request so
 * nothing is added after it reached a terminal status; does not change the
 * state version. The message id is the history cursor.
 */
class AppendRequestMessage
{
    use RunsIdempotently;

    /**
     * @return array{result: RequestMessage, replayed: bool}
     */
    public function forGuest(GuestContext $guest, GuestRequest $request, string $text, string $key): array
    {
        $text = $this->clean($text);
        $fingerprint = Fingerprint::of(['actor' => $guest->guestSessionId, 'request' => $request->public_id, 'text' => $text, 'visibility' => 'public']);

        return $this->idempotent(OperationReceipt::ACTOR_GUEST, $guest->guestSessionId, Receipts::MESSAGE, $key, $fingerprint, function () use ($guest, $request, $text) {
            $request = $this->lock($request);

            if (! RequestAccess::guestMayAct($guest, $request)) {
                abort(404);
            }
            $this->assertOpen($request);

            $message = RequestMessage::create([
                'request_id' => $request->id,
                'author_type' => RequestMessage::AUTHOR_GUEST,
                'visibility' => MessageVisibility::Public,
                'text' => $text,
                'created_at' => now(),
            ]);
            $request->touch();

            RequestNotifier::guestMessage($request);

            return [$message, 'request_message', (string) $message->id];
        }, fn ($receipt) => RequestMessage::query()->findOrFail((int) $receipt->resource_public_id));
    }

    /**
     * @return array{result: RequestMessage, replayed: bool}
     */
    public function forStaff(User $author, GuestRequest $request, string $text, MessageVisibility $visibility, string $key): array
    {
        $text = $this->clean($text);
        $fingerprint = Fingerprint::of(['actor' => $author->id, 'request' => $request->public_id, 'text' => $text, 'visibility' => $visibility->value]);

        return $this->idempotent(OperationReceipt::ACTOR_USER, $author->id, Receipts::MESSAGE, $key, $fingerprint, function () use ($author, $request, $text, $visibility) {
            $request = $this->lock($request);

            if (! RequestAccess::staffCanHandle(ActorContext::load($author), $request)) {
                abort(404);
            }
            $this->assertOpen($request);

            $message = RequestMessage::create([
                'request_id' => $request->id,
                'author_type' => RequestMessage::AUTHOR_STAFF,
                'author_user_id' => $author->id,
                'visibility' => $visibility,
                'text' => $text,
                'created_at' => now(),
            ]);
            $request->touch();

            return [$message, 'request_message', (string) $message->id];
        }, fn ($receipt) => RequestMessage::query()->findOrFail((int) $receipt->resource_public_id));
    }

    private function clean(string $text): string
    {
        return trim(str_replace("\r\n", "\n", $text));
    }

    private function lock(GuestRequest $request): GuestRequest
    {
        /** @var GuestRequest $locked */
        $locked = GuestRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

        return $locked;
    }

    private function assertOpen(GuestRequest $request): void
    {
        if ($request->isTerminal()) {
            throw new ApiException('request_closed', 409);
        }
    }
}
