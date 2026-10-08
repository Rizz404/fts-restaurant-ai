<?php

use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\InfoController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PreferenceController;
use App\Http\Controllers\Api\ReadinessController;
use App\Http\Controllers\Api\RequestController;
use App\Http\Controllers\Api\RequestDraftController;
use App\Http\Controllers\Api\StaffRequestController;
use App\Http\Middleware\EnsureGuestSession;
use App\Http\Middleware\RejectHoneypot;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Internal JSON API (/api/v1)
|--------------------------------------------------------------------------
|
| Loaded inside the web middleware group by routes/web.php, so endpoints use
| the session and CSRF protection. Responses use App\Support\Http\ApiResponse.
| Guest endpoints need no login; the guest session row is only created by
| the endpoints that write (EnsureGuestSession). Staff endpoints use the
| `account` group. Business endpoints are added by the plan that owns them.
|
*/

// Locale preference: guests and staff.
Route::post('preferences', [PreferenceController::class, 'update'])->name('preferences.update');

// Public read-only menu and info (throttled per IP).
Route::middleware('throttle:public-read')->group(function () {
    Route::get('menu', [MenuController::class, 'index'])->name('menu.index');
    Route::get('menu/{slug}', [MenuController::class, 'show'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('menu.show');
    Route::get('info', [InfoController::class, 'index'])->name('info.index');
    Route::get('info/{slug}', [InfoController::class, 'show'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('info.show');
    Route::get('availability', AvailabilityController::class)->name('availability');
});

// Guest AI conversation. Writes: IP bound before the session row exists, honeypot, lazy guest
// session, then the per-session limiter. Reads never create a guest session.
Route::middleware(['throttle:guest-session-create', RejectHoneypot::class, EnsureGuestSession::class])->group(function () {
    Route::post('conversations', [ConversationController::class, 'store'])
        ->middleware('throttle:guest-assistant')
        ->name('conversations.store');
    Route::post('conversations/{conversation}/messages', [ConversationController::class, 'sendMessage'])
        ->middleware('throttle:guest-assistant')
        ->whereUuid('conversation')
        ->name('conversations.messages.store');
});

Route::get('conversations/{conversation}/messages', [ConversationController::class, 'history'])->whereUuid('conversation')->name('conversations.messages.index');
Route::get('conversations/{conversation}/turns/{turn}', [ConversationController::class, 'turn'])->whereUuid(['conversation', 'turn'])->name('conversations.turns.show');

// Guest requests. Writes: IP bound before the session row exists, honeypot, lazy guest session,
// then the per-session limiter. Reads never create a guest session; other guests' data is 404.
Route::middleware(['throttle:guest-session-create', RejectHoneypot::class, EnsureGuestSession::class, 'throttle:guest-writes'])->group(function () {
    Route::post('request-drafts', [RequestDraftController::class, 'store'])->name('request-drafts.store');
    Route::patch('request-drafts/{draft}', [RequestDraftController::class, 'update'])->whereUuid('draft')->name('request-drafts.update');
    Route::post('request-drafts/{draft}/confirm', [RequestDraftController::class, 'confirm'])->whereUuid('draft')->name('request-drafts.confirm');
    Route::post('requests/{guestRequest}/messages', [RequestController::class, 'storeMessage'])->whereUuid('guestRequest')->name('requests.messages.store');
    Route::post('requests/{guestRequest}/cancel', [RequestController::class, 'cancel'])->whereUuid('guestRequest')->name('requests.cancel');
});

// Reference + contact lookup: honeypot and a per-IP limiter; failures are uniform and locked per reference.
Route::post('requests/lookup', [RequestController::class, 'lookup'])
    ->middleware(['throttle:guest-lookup', RejectHoneypot::class])
    ->name('requests.lookup');

Route::get('request-drafts/{draft}', [RequestDraftController::class, 'show'])->whereUuid('draft')->name('request-drafts.show');
Route::get('requests', [RequestController::class, 'index'])->name('requests.index');
Route::get('requests/{guestRequest}', [RequestController::class, 'show'])->whereUuid('guestRequest')->name('requests.show');
Route::get('requests/{guestRequest}/messages', [RequestController::class, 'messages'])->whereUuid('guestRequest')->name('requests.messages.index');

// Staff: active account, role staff with a team (requests.staff gate); other teams' requests are 404.
Route::middleware(['account', 'can:requests.staff'])->prefix('staff')->name('staff.')->group(function () {
    Route::middleware('throttle:staff-writes')->group(function () {
        Route::patch('requests/{guestRequest}/assignment', [StaffRequestController::class, 'assign'])->whereUuid('guestRequest')->name('requests.assignment.update');
        Route::patch('requests/{guestRequest}/status', [StaffRequestController::class, 'updateStatus'])->whereUuid('guestRequest')->name('requests.status.update');
        Route::post('requests/{guestRequest}/messages', [StaffRequestController::class, 'storeMessage'])->whereUuid('guestRequest')->name('requests.messages.store');
    });
    Route::get('requests/{guestRequest}/messages', [StaffRequestController::class, 'messages'])->whereUuid('guestRequest')->name('requests.messages.index');
});

Route::middleware('account')->group(function () {
    Route::get('admin/readiness', ReadinessController::class)->middleware('can:operations.view')->name('admin.readiness');
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('notifications/{notification}/read', [NotificationController::class, 'markRead'])->whereUuid('notification')->name('notifications.read');
});
