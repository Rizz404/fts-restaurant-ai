<?php

namespace App\Modules\Requests\Actions;

use App\Models\GuestRequest;
use App\Models\OperationReceipt;
use App\Models\RequestEvent;
use App\Models\User;
use App\Modules\Conversation\Support\Fingerprint;
use App\Modules\Identity\ActorContext;
use App\Modules\Requests\Actions\Concerns\RunsIdempotently;
use App\Modules\Requests\Support\Receipts;
use App\Modules\Requests\Support\RequestAccess;
use App\Support\Http\ApiException;

/**
 * Staff of the request's team assign an active staff member of that same
 * team. Row lock + expected_version; bumps the version.
 */
class AssignRequest
{
    use RunsIdempotently;

    /**
     * @return array{result: GuestRequest, replayed: bool}
     */
    public function handle(User $actor, GuestRequest $request, int $assigneeId, int $expectedVersion, string $key): array
    {
        $fingerprint = Fingerprint::of(['actor' => $actor->id, 'request' => $request->public_id, 'assignee' => $assigneeId, 'version' => $expectedVersion]);

        return $this->idempotent(OperationReceipt::ACTOR_USER, $actor->id, Receipts::ASSIGN, $key, $fingerprint, function () use ($actor, $request, $assigneeId, $expectedVersion) {
            /** @var GuestRequest $request */
            $request = GuestRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if (! RequestAccess::staffCanHandle(ActorContext::load($actor), $request)) {
                abort(404);
            }
            if ($request->isTerminal()) {
                throw new ApiException('request_closed', 409);
            }
            if ($request->version !== $expectedVersion) {
                throw new ApiException('version_conflict', 409);
            }

            $assignee = User::query()->find($assigneeId);
            if ($assignee === null || ! RequestAccess::staffCanHandle(ActorContext::load($assignee), $request)) {
                throw new ApiException('validation_failed', 422, ['assignee_id' => [__('requests.errors.invalid_assignee')]]);
            }

            $previous = $request->assignee_id;
            $request->forceFill(['assignee_id' => $assignee->id, 'version' => $request->version + 1])->save();

            RequestEvent::create([
                'request_id' => $request->id,
                'actor_type' => RequestEvent::ACTOR_STAFF,
                'actor_user_id' => $actor->id,
                'event_code' => 'assigned',
                'metadata' => ['from_assignee_id' => $previous, 'to_assignee_id' => $assignee->id],
                'created_at' => now(),
            ]);

            return [$request, 'request', $request->public_id];
        }, fn ($receipt) => GuestRequest::query()->where('public_id', $receipt->resource_public_id)->firstOrFail());
    }
}
