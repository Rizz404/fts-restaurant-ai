<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\SetLocale;
use App\Http\Requests\Api\UpdatePreferencesRequest;
use App\Modules\Identity\Guest\GuestSessions;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Locale preference. Signed-in staff keep preferred_locale; everyone stores
 * the choice in the session, and an existing guest session row follows it.
 * Never creates a guest session row.
 */
class PreferenceController extends Controller
{
    public function update(UpdatePreferencesRequest $request, GuestSessions $guests): JsonResponse
    {
        $locale = $request->validated('locale');

        $user = $request->user();
        if ($user !== null) {
            $user->forceFill(['preferred_locale' => $locale])->save();
        }

        $request->session()->put(SetLocale::SESSION_KEY, $locale);
        $guests->syncLocale($locale);
        app()->setLocale($locale);

        return ApiResponse::success(['locale' => $locale]);
    }
}
