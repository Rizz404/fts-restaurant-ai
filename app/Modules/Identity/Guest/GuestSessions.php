<?php

namespace App\Modules\Identity\Guest;

use App\Http\Middleware\SetLocale;
use App\Models\GuestSession;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Resolves the anonymous guest session of the current request.
 *
 * The server session stores only the guest session ID. The row is created
 * lazily on the first write (AI turn, draft, confirmation), never on a page
 * view. A staff member browsing guest pages is an ordinary guest: the guest
 * session is independent of the staff account and carries no staff rights.
 *
 * The service holds no state of its own; the resolved row is cached on the
 * current request so it is safe across requests in one process.
 */
final class GuestSessions
{
    public const SESSION_KEY = 'guest_session_id';

    public const TABLE_LABEL_KEY = 'table_label';

    private const ATTRIBUTE = 'guest.session';

    /** Seconds between last_seen_at refreshes, to avoid a write on every request. */
    private const TOUCH_INTERVAL = 300;

    public static function isValidTableLabel(mixed $value): bool
    {
        return is_string($value) && preg_match('/\A[A-Za-z0-9-]{1,20}\z/', $value) === 1;
    }

    /**
     * The guest session for this request, or null if none exists and
     * $create is false.
     */
    public function current(bool $create = false): ?GuestSession
    {
        $request = $this->request();
        $session = $request->session();
        $cached = $request->attributes->get(self::ATTRIBUTE);

        if ($cached === null) {
            $cached = false;
            $id = $session->get(self::SESSION_KEY);

            if (is_int($id)) {
                $row = GuestSession::query()->find($id);

                if ($row !== null && $row->expires_at->isFuture()) {
                    $cached = $this->touch($row);
                } else {
                    $session->forget(self::SESSION_KEY);
                }
            }

            $request->attributes->set(self::ATTRIBUTE, $cached);
        }

        if ($cached === false && $create) {
            $cached = $this->create($session);
            $request->attributes->set(self::ATTRIBUTE, $cached);
        }

        return $cached === false ? null : $cached;
    }

    public function context(bool $create = false): ?GuestContext
    {
        $row = $this->current($create);

        return $row === null ? null : GuestContext::fromSession($row);
    }

    /**
     * Applies a locale change to the stored row, if one exists.
     */
    public function syncLocale(string $locale): void
    {
        $row = $this->current();

        if ($row !== null && $row->locale !== $locale) {
            $row->forceFill(['locale' => $locale])->save();
        }
    }

    private function create(Session $session): GuestSession
    {
        $now = now();
        $label = $session->get(self::TABLE_LABEL_KEY);

        $row = GuestSession::query()->create([
            'public_id' => (string) Str::uuid(),
            'locale' => $this->locale($session),
            'table_label' => self::isValidTableLabel($label) ? $label : null,
            'created_at' => $now,
            'last_seen_at' => $now,
            'expires_at' => $now->copy()->addDays((int) config('restaurant.retention.guest_session_days')),
        ]);

        $session->put(self::SESSION_KEY, $row->getKey());

        return $row;
    }

    private function touch(GuestSession $row): GuestSession
    {
        if ($row->last_seen_at->diffInSeconds(now(), true) >= self::TOUCH_INTERVAL) {
            $now = now();
            $row->forceFill([
                'last_seen_at' => $now,
                'expires_at' => $now->copy()->addDays((int) config('restaurant.retention.guest_session_days')),
            ])->save();
        }

        return $row;
    }

    private function locale(Session $session): string
    {
        $locale = $session->get(SetLocale::SESSION_KEY);

        return in_array($locale, config('restaurant.locales'), true) ? $locale : config('restaurant.default_locale');
    }

    private function request(): Request
    {
        return request();
    }
}
