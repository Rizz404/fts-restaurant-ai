<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers for the public site (PLAN_08 "Hardening situs publik").
 *
 * Content-Security-Policy: everything comes from this origin. Alpine 3
 * evaluates template expressions with `new Function`, so script-src needs
 * 'unsafe-eval' (decision recorded in docs/PLAN_08: the @alpinejs/csp build
 * would force every inline expression of the UI into component methods).
 * The compensation is that templates never use x-html and every dynamic
 * text goes through x-text/textContent. Inline style *attributes* (CSS
 * custom properties on slides) are allowed through style-src-attr only;
 * <style> elements and style sheets from other origins are not. JSON script
 * blocks (type="application/json") are data, not executed.
 *
 * The Vite dev server (public/hot) needs other origins, so the policy is
 * skipped while it runs. HSTS is only sent on HTTPS requests.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = $response->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $headers->set('X-Frame-Options', 'DENY');

        if (config('restaurant.security.csp') && ! file_exists(public_path('hot'))) {
            $headers->set('Content-Security-Policy', $this->policy());
        }

        if (config('restaurant.security.hsts') && $request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    public function policy(): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-eval'",
            "style-src 'self'",
            "style-src-attr 'unsafe-inline'",
            "img-src 'self' data:",
            "font-src 'self'",
            "connect-src 'self'",
            "media-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);
    }
}
