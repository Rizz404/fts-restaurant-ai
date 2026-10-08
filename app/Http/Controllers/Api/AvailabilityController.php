<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Requests\Actions\CheckAvailability;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only reservation slots for a date and party size (public, throttled
 * per IP). Does not create a guest session.
 */
class AvailabilityController extends Controller
{
    public function __invoke(Request $request, CheckAvailability $availability): JsonResponse
    {
        $input = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'party_size' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $result = $availability->handle($input['date'], (int) $input['party_size']);

        return ApiResponse::success($result['slots'], [
            'date' => $result['date'],
            'party_size' => $result['party_size'],
            'capacity' => $result['capacity'],
            'closed' => $result['closed'],
        ]);
    }
}
