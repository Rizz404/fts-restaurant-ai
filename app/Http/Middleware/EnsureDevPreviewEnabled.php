<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Second guard for /dev/*: the routes are only loaded in local/testing, and
 * this middleware returns 404 if the environment changes at runtime.
 */
class EnsureDevPreviewEnabled
{
    public const ENVIRONMENTS = ['local', 'testing'];

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(app()->environment(self::ENVIRONMENTS), 404);

        return $next($request);
    }
}
