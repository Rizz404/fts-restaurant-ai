<?php

namespace App\Modules\Conversation\Support;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;

/**
 * Cache-backed leases (database store) that allow one active AI turn per
 * guest session and per conversation. Leases expire after
 * assistant.lease_seconds; a process must still own both before committing
 * a model response.
 */
final class TurnLease
{
    private function __construct(
        private readonly Lock $guestLock,
        private readonly Lock $conversationLock,
    ) {}

    public static function acquire(int $guestSessionId, int $conversationId): ?self
    {
        $seconds = (int) config('assistant.lease_seconds');
        $guest = Cache::lock(self::guestKey($guestSessionId), $seconds);

        if (! $guest->get()) {
            return null;
        }

        $conversation = Cache::lock(self::conversationKey($conversationId), $seconds);
        if (! $conversation->get()) {
            $guest->release();

            return null;
        }

        return new self($guest, $conversation);
    }

    public function stillOwned(): bool
    {
        return $this->guestLock->isOwnedByCurrentProcess() && $this->conversationLock->isOwnedByCurrentProcess();
    }

    public function release(): void
    {
        $this->conversationLock->release();
        $this->guestLock->release();
    }

    /**
     * Used by recovery: a stuck turn loses its lease so a running process
     * cannot commit stale output.
     */
    public static function forceReleaseForConversation(int $conversationId): void
    {
        Cache::lock(self::conversationKey($conversationId))->forceRelease();
    }

    public static function forceReleaseForGuest(int $guestSessionId): void
    {
        Cache::lock(self::guestKey($guestSessionId))->forceRelease();
    }

    private static function guestKey(int $guestSessionId): string
    {
        return "assistant:lease:guest:{$guestSessionId}";
    }

    private static function conversationKey(int $conversationId): string
    {
        return "assistant:lease:conversation:{$conversationId}";
    }
}
