<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Modules\Identity\Actions\ChangePassword;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PasswordController extends Controller
{
    public function edit(Request $request): View
    {
        return view('auth.change-password', [
            'mustChange' => $request->user()->must_change_password,
        ]);
    }

    public function update(ChangePasswordRequest $request, ChangePassword $changePassword): RedirectResponse
    {
        $user = $changePassword->handle($request->user(), $request->validated('password'));

        // Keep this session valid under AuthenticateSession; other sessions
        // still carry the old password hash and are logged out.
        $request->session()->regenerate();
        $request->session()->put('password_hash_web', Auth::guard('web')->hashPasswordForCookie($user->getAuthPassword()));

        return redirect()->route('admin.dashboard')->with('status', __('ui.auth.password_changed'));
    }
}
