<?php

namespace App\Modules\Identity\Guest;

use App\Models\GuestSession;

/**
 * Per-request identity of an anonymous guest, built from the server-side
 * guest session row. Identity never comes from request bodies, scene
 * context, or AI tool arguments.
 */
final class GuestContext
{
    public function __construct(
        public readonly int $guestSessionId,
        public readonly string $publicId,
        public readonly string $locale,
        public readonly ?string $tableLabel,
    ) {}

    public static function fromSession(GuestSession $session): self
    {
        return new self(
            guestSessionId: $session->getKey(),
            publicId: $session->public_id,
            locale: $session->locale,
            tableLabel: $session->table_label,
        );
    }
}
