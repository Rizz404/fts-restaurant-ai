<?php

namespace App\Support\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * Builds the shared JSON envelope for /api/v1 endpoints.
 */
final class ApiResponse
{
    public const SCHEMA_VERSION = 1;

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, string>  $headers
     */
    public static function success(mixed $data, array $meta = [], int $status = 200, array $headers = []): JsonResponse
    {
        return self::make($data, null, $meta, $status, $headers);
    }

    /**
     * @param  array<string, list<string>>  $fields
     * @param  array<string, string>  $headers
     */
    public static function error(string $code, string $message, int $status, array $fields = [], array $headers = []): JsonResponse
    {
        return self::make(null, [
            'code' => $code,
            'message' => $message,
            'fields' => (object) $fields,
        ], [], $status, $headers);
    }

    public static function requestId(): string
    {
        $request = request();
        $id = $request->attributes->get('request_id');

        if (! is_string($id)) {
            $id = (string) Str::uuid();
            $request->attributes->set('request_id', $id);
        }

        return $id;
    }

    /**
     * @param  array<string, mixed>|null  $error
     * @param  array<string, mixed>  $meta
     * @param  array<string, string>  $headers
     */
    private static function make(mixed $data, ?array $error, array $meta, int $status, array $headers): JsonResponse
    {
        $requestId = self::requestId();

        return new JsonResponse([
            'schema_version' => self::SCHEMA_VERSION,
            'data' => $data,
            'meta' => ['request_id' => $requestId] + $meta,
            'error' => $error,
        ], $status, ['X-Request-Id' => $requestId] + $headers);
    }
}
