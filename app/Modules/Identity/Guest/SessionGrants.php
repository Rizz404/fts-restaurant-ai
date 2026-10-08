<?php

namespace App\Modules\Identity\Guest;

/**
 * Request public IDs this browser session opened with a reference code and
 * matching contact (Plan 6). Stored in the server session, newest last,
 * capped so a session cannot collect an unbounded set of requests.
 */
final class SessionGrants
{
    public const SESSION_KEY = 'request_grants';

    public const MAX = 20;

    /**
     * @return list<string>
     */
    public function all(): array
    {
        // No session (console, the evaluation command): nothing was granted.
        if (! request()->hasSession()) {
            return [];
        }

        $grants = request()->session()->get(self::SESSION_KEY, []);

        return is_array($grants) ? array_values(array_filter($grants, 'is_string')) : [];
    }

    public function has(string $requestPublicId): bool
    {
        return in_array($requestPublicId, $this->all(), true);
    }

    public function add(string $requestPublicId): void
    {
        if (! request()->hasSession()) {
            return;
        }

        $grants = array_values(array_diff($this->all(), [$requestPublicId]));
        $grants[] = $requestPublicId;

        request()->session()->put(self::SESSION_KEY, array_slice($grants, -self::MAX));
    }
}
