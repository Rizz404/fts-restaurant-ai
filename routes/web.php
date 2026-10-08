<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\InfoController as AdminInfoController;
use App\Http\Controllers\Admin\MenuCategoryController;
use App\Http\Controllers\Admin\MenuController as AdminMenuController;
use App\Http\Controllers\Admin\OperationsController;
use App\Http\Controllers\Admin\RequestController as AdminRequestController;
use App\Http\Controllers\Admin\RestaurantSettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\BookingsController;
use App\Http\Controllers\ExperienceController;
use App\Http\Controllers\InfoController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ReservationController;
use App\Http\Middleware\EnsureDevPreviewEnabled;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Guest deck (public, no login)
|--------------------------------------------------------------------------
|
| Page views never create a guest session row. Slides with domain content
| (menu, info, reservation, order, bookings) are added by their plans.
|
*/

Route::get('/', [ExperienceController::class, 'welcome'])->name('experience.welcome');

$slug = '[a-z0-9]+(?:-[a-z0-9]+)*';

Route::get('menu', [MenuController::class, 'index'])->name('experience.menu');
Route::get('menu/{slug}', [MenuController::class, 'show'])->where('slug', $slug)->name('experience.menu.show');
Route::get('picks', [MenuController::class, 'picks'])->name('experience.picks');
Route::get('info', [InfoController::class, 'index'])->name('experience.info');
Route::get('info/{slug}', [InfoController::class, 'show'])->where('slug', $slug)->name('experience.info.show');
Route::get('reservation', ReservationController::class)->name('experience.reservation');
Route::get('order', OrderController::class)->name('experience.order');
Route::get('bookings', [BookingsController::class, 'index'])->name('experience.bookings');
Route::get('bookings/{reference}', [BookingsController::class, 'show'])->where('reference', '[A-Za-z0-9-]{1,40}')->name('experience.bookings.show');

/*
|--------------------------------------------------------------------------
| Staff sign-in and admin area
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('account.active')->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('password/change', [PasswordController::class, 'edit'])->name('password.change');
    Route::post('password/change', [PasswordController::class, 'update'])->name('password.update');
});

Route::middleware('account')->group(function () {
    Route::prefix('admin')->name('admin.')->middleware('can:admin.access')->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::resource('users', UserController::class)->except(['show', 'destroy']);
        Route::post('users/{user}/reset-password', [UserPasswordController::class, 'store'])->name('users.reset-password');

        // Menu CMS: admin and editor manage; staff only change availability (see MenuItemPolicy).
        Route::prefix('menu')->name('menu.')->group(function () {
            Route::get('categories', [MenuCategoryController::class, 'index'])->name('categories.index');
            Route::get('categories/create', [MenuCategoryController::class, 'create'])->name('categories.create');
            Route::post('categories', [MenuCategoryController::class, 'store'])->name('categories.store');
            Route::get('categories/{category}/edit', [MenuCategoryController::class, 'edit'])->whereNumber('category')->name('categories.edit');
            Route::patch('categories/{category}', [MenuCategoryController::class, 'update'])->whereNumber('category')->name('categories.update');

            Route::get('/', [AdminMenuController::class, 'index'])->name('index');
            Route::get('create', [AdminMenuController::class, 'create'])->name('create');
            Route::post('/', [AdminMenuController::class, 'store'])->name('store');
            Route::get('{item}/edit', [AdminMenuController::class, 'edit'])->whereNumber('item')->name('edit');
            Route::patch('{item}', [AdminMenuController::class, 'update'])->whereNumber('item')->name('update');
            Route::post('{item}/publication', [AdminMenuController::class, 'publish'])->whereNumber('item')->name('publication.store');
            Route::delete('{item}/publication', [AdminMenuController::class, 'archive'])->whereNumber('item')->name('publication.destroy');
            Route::patch('{item}/availability', [AdminMenuController::class, 'availability'])->whereNumber('item')->name('availability');
            Route::post('{item}/allergen-verification', [AdminMenuController::class, 'verifyAllergens'])->whereNumber('item')->name('verification.store');
        });

        // Info CMS: admin and editor write drafts; only admin publishes and archives.
        Route::prefix('info')->name('info.')->group(function () {
            Route::get('/', [AdminInfoController::class, 'index'])->name('index');
            Route::get('create', [AdminInfoController::class, 'create'])->name('create');
            Route::post('/', [AdminInfoController::class, 'store'])->name('store');
            Route::get('{article}/edit', [AdminInfoController::class, 'edit'])->whereNumber('article')->name('edit');
            Route::patch('{article}/draft', [AdminInfoController::class, 'update'])->whereNumber('article')->name('draft.update');
            Route::post('{article}/publication', [AdminInfoController::class, 'publish'])->whereNumber('article')->name('publication.store');
            Route::delete('{article}/publication', [AdminInfoController::class, 'archive'])->whereNumber('article')->name('publication.destroy');
            Route::get('{article}/revisions', [AdminInfoController::class, 'revisions'])->whereNumber('article')->name('revisions.index');
            Route::post('{article}/revisions', [AdminInfoController::class, 'createDraft'])->whereNumber('article')->name('revisions.store');
        });

        // Request panel: current team membership only (the admin role alone sees nothing).
        Route::middleware('can:requests.staff')->prefix('requests')->name('requests.')->group(function () {
            Route::get('/', [AdminRequestController::class, 'index'])->name('index');
            Route::get('{guestRequest}', [AdminRequestController::class, 'show'])->whereUuid('guestRequest')->name('show');
        });

        Route::get('operations', OperationsController::class)->middleware('can:operations.view')->name('operations');

        Route::get('settings', [RestaurantSettingsController::class, 'edit'])->name('settings.edit');
        Route::patch('settings', [RestaurantSettingsController::class, 'update'])->name('settings.update');
    });
});

// JSON endpoints share the web group (session + CSRF), see TECHNICAL_CONTRACTS.md.
Route::prefix('api/v1')->name('api.')->group(base_path('routes/internal.php'));

if (app()->environment(EnsureDevPreviewEnabled::ENVIRONMENTS)) {
    Route::prefix('dev')->name('dev.')->middleware(EnsureDevPreviewEnabled::class)->group(base_path('routes/dev.php'));
}
