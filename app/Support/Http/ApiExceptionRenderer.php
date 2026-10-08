<?php

namespace App\Support\Http;

use App\Modules\Operations\Support\RateLimitHits;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Maps exceptions on /api/* to the shared error envelope.
 */
final class ApiExceptionRenderer
{
    /** @var array<int, string> */
    private const STATUS_CODES = [
        400 => 'bad_request',
        401 => 'unauthenticated',
        403 => 'forbidden',
        404 => 'not_found',
        405 => 'method_not_allowed',
        409 => 'conflict',
        419 => 'session_expired',
        422 => 'validation_failed',
        429 => 'too_many_requests',
        503 => 'service_unavailable',
    ];

    public static function shouldRender(Request $request): bool
    {
        return $request->is('api/*');
    }

    public static function render(Throwable $e): JsonResponse
    {
        return match (true) {
            $e instanceof ApiException => self::respond($e->errorCode, $e->status, $e->fields),
            $e instanceof ValidationException => self::respond('validation_failed', 422, $e->errors()),
            $e instanceof AuthenticationException => self::respond('unauthenticated', 401),
            $e instanceof AuthorizationException => self::respond('forbidden', $e->status() ?? 403),
            $e instanceof ModelNotFoundException => self::respond('not_found', 404),
            $e instanceof TokenMismatchException => self::respond('session_expired', 419),
            $e instanceof HttpExceptionInterface => self::respond(
                self::STATUS_CODES[$e->getStatusCode()] ?? ($e->getStatusCode() >= 500 ? 'server_error' : 'bad_request'),
                $e->getStatusCode(),
                headers: $e->getHeaders(),
            ),
            default => self::respond('server_error', 500),
        };
    }

    /**
     * @param  array<string, list<string>>  $fields
     * @param  array<string, string>  $headers
     */
    private static function respond(string $code, int $status, array $fields = [], array $headers = []): JsonResponse
    {
        if ($status === 429) {
            RateLimitHits::record();
        }

        $key = 'ui.errors.'.$code;
        $message = trans()->has($key) ? __($key) : __('ui.errors.server_error');

        return ApiResponse::error($code, $message, $status, $fields, $headers);
    }
}
