<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Operations\Support\Readiness;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Readiness for admins (database, cache, provider settings, storage, and
 * scheduler/worker heartbeats). Never calls the model; 503 only when a
 * required check fails, so an unavailable AI does not mark the app as down.
 */
class ReadinessController extends Controller
{
    public function __invoke(Readiness $readiness): JsonResponse
    {
        $result = $readiness->handle();

        return ApiResponse::success($result, status: $result['ready'] ? 200 : 503);
    }
}
