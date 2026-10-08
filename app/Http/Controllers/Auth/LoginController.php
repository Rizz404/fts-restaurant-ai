<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\SetLocale;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        $locale = $request->user()->preferred_locale;
        if (in_array($locale, config('restaurant.locales'), true)) {
            $request->session()->put(SetLocale::SESSION_KEY, $locale);
        }

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $locale = $request->session()->get(SetLocale::SESSION_KEY);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($locale !== null) {
            $request->session()->put(SetLocale::SESSION_KEY, $locale);
        }

        return redirect()->route('login')->with('status', __('ui.auth.logged_out'));
    }
}
