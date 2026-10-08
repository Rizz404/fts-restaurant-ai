<?php

use App\Http\Controllers\Dev\PreviewController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Development previews (/dev/*)
|--------------------------------------------------------------------------
|
| Loaded only when APP_ENV is local or testing. Synthetic data only; no
| accounts, database reads, or business writes.
|
*/

Route::get('ui', [PreviewController::class, 'gallery'])->name('ui');
Route::get('admin', [PreviewController::class, 'admin'])->name('admin');
Route::get('user', [PreviewController::class, 'user'])->name('user');
Route::get('guest', [PreviewController::class, 'guest'])->name('guest');
