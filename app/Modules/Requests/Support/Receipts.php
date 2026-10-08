<?php

namespace App\Modules\Requests\Support;

use App\Models\OperationReceipt;
use App\Support\Http\ApiException;

/**
 * Idempotency receipts: unique per actor (guest session or user) + operation
 * type + key. Same key and same payload fingerprint replays the first
 * result; same key with a different payload is a 409.
 */
final class Receipts
{
    public const CONFIRM_DRAFT = 'request_draft.confirm';

    public const MESSAGE = 'request.message';

    public const CANCEL = 'request.cancel';

    public const TRANSITION = 'request.transition';

    public const ASSIGN = 'request.assign';

    public static function find(string $actorType, int $actorId, string $type, string $key): ?OperationReceipt
    {
        return OperationReceipt::query()
            ->where('actor_type', $actorType)
            ->where('actor_id', $actorId)
            ->where('operation_type', $type)
            ->where('idempotency_key', strtolower($key))
            ->first();
    }

    /**
     * Returns the receipt when the key was used before with the same payload.
     *
     * @throws ApiException
     */
    public static function replay(string $actorType, int $actorId, string $type, string $key, string $fingerprint): ?OperationReceipt
    {
        $receipt = self::find($actorType, $actorId, $type, $key);

        if ($receipt !== null && ! hash_equals($receipt->fingerprint, $fingerprint)) {
            throw new ApiException('idempotency_conflict', 409);
        }

        return $receipt;
    }

    /**
     * Records the receipt; call inside the operation's transaction.
     */
    public static function record(string $actorType, int $actorId, string $type, string $key, string $fingerprint, string $resourceType, string $resourcePublicId): void
    {
        OperationReceipt::create([
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'operation_type' => $type,
            'idempotency_key' => strtolower($key),
            'fingerprint' => $fingerprint,
            'resource_type' => $resourceType,
            'resource_public_id' => $resourcePublicId,
            'created_at' => now(),
        ]);
    }
}
