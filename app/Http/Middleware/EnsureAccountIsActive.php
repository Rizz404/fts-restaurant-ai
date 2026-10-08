<?php

namespace App\Http\Middleware;

use App\Support\Http\ApiException;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs out a deactivated user on their next request. The user is re-read
 * from the database by the session guard on every request.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->is('api/*') || $request->expectsJson()) {
                throw new ApiException('unauthenticated', 401);
            }

            return redirect()->route('login')->with('status', __('ui.auth.account_inactive'));
        }

        return $next($request);
    }
}
