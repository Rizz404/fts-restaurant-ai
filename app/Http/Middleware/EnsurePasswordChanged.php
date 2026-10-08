<?php

namespace App\Http\Middleware;

use App\Support\Http\ApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Users with a temporary password can only reach the password change page
 * and logout until they set their own password.
 */
class EnsurePasswordChanged
{
    private const ALLOWED_ROUTES = ['password.change', 'password.update', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->must_change_password && ! $request->routeIs(...self::ALLOWED_ROUTES)) {
            if ($request->is('api/*') || $request->expectsJson()) {
                throw new ApiException('password_change_required', 403);
            }

            return redirect()->route('password.change');
        }

        return $next($request);
    }
}
