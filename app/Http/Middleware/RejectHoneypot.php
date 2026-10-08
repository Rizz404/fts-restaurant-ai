<?php

namespace App\Http\Middleware;

use App\Modules\Identity\Guest\GuestChallenge;
use App\Support\Http\ApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects guest form/API submissions that fail the GuestChallenge (by
 * default: the hidden honeypot field is filled). The response is a plain
 * validation failure with no hint about why.
 */
class RejectHoneypot
{
    public const FIELD = 'contact_website';

    public function __construct(private readonly GuestChallenge $challenge) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->challenge->passes($request)) {
            if ($request->is('api/*') || $request->expectsJson()) {
                throw new ApiException('validation_failed', 422);
            }

            abort(422);
        }

        return $next($request);
    }
}
