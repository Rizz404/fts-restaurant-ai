<?php

namespace App\Modules\Requests\Actions;

use App\Models\GuestRequest;
use App\Modules\Identity\Guest\SessionGrants;
use App\Modules\Requests\Support\RequestReference;
use App\Modules\Requests\Validation\RequestDraftRules;
use App\Support\Http\ApiException;
use Illuminate\Support\Facades\Cache;

/**
 * Opens a request by reference + the contact entered at confirmation (phone
 * digits or lowercase email). A match adds a grant to the guest's server
 * session (at most 20). Failures are uniform (404 lookup_failed) whether the
 * reference exists or not, and five failures in a row for one reference lock
 * lookups for that reference for 15 minutes (still the same uniform error).
 * A per-IP limiter (guest-lookup) wraps this at the route.
 */
class LookupRequest
{
    public const MAX_FAILURES = 5;

    public const LOCK_MINUTES = 15;

    public function __construct(private readonly SessionGrants $grants) {}

    public function handle(string $reference, string $contact): GuestRequest
    {
        $reference = RequestReference::normalize($reference);
        $key = 'request-lookup:'.hash('sha256', $reference);

        if (! RequestReference::isValid($reference) || $this->locked($key)) {
            $this->fail($key);
        }

        $request = GuestRequest::query()->where('reference', $reference)->first();

        if ($request === null || ! $this->matches($request, $contact)) {
            $this->fail($key);
        }

        Cache::forget($key.':failures');
        Cache::forget($key.':locked');
        $this->grants->add($request->public_id);

        return $request;
    }

    private function matches(GuestRequest $request, string $contact): bool
    {
        $contact = trim($contact);

        if (str_contains($contact, '@')) {
            return $request->contact_email !== null && hash_equals($request->contact_email, mb_strtolower($contact));
        }

        $digits = RequestDraftRules::phoneDigits($contact);

        return $digits !== null && strlen($digits) >= 6 && $request->contact_phone_digits !== null && hash_equals($request->contact_phone_digits, $digits);
    }

    private function locked(string $key): bool
    {
        return Cache::has($key.':locked');
    }

    private function fail(string $key): never
    {
        $failures = (int) Cache::get($key.':failures', 0) + 1;
        Cache::put($key.':failures', $failures, now()->addMinutes(self::LOCK_MINUTES));

        if ($failures >= self::MAX_FAILURES) {
            Cache::put($key.':locked', true, now()->addMinutes(self::LOCK_MINUTES));
        }

        throw new ApiException('lookup_failed', 404);
    }
}
