<?php

namespace App\Http\Middleware;

use App\Modules\Identity\Guest\GuestSessions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * For guest endpoints that write: makes sure the anonymous guest session
 * row exists (created lazily here, not on page views). Staff using guest
 * endpoints are ordinary guests; the guest session carries no staff rights.
 */
class EnsureGuestSession
{
    public function __construct(private readonly GuestSessions $guests) {}

    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('guest', $this->guests->context(create: true));

        return $next($request);
    }
}
