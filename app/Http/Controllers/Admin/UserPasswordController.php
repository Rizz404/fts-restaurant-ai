<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResetUserPasswordRequest;
use App\Models\User;
use App\Modules\Identity\Actions\ResetTemporaryPassword;
use Illuminate\Http\RedirectResponse;

class UserPasswordController extends Controller
{
    public function store(ResetUserPasswordRequest $request, User $user, ResetTemporaryPassword $reset): RedirectResponse
    {
        $reset->handle($request->user(), $user, $request->validated('password'));

        return redirect()->route('admin.users.edit', $user)->with('status', __('ui.users.password_reset'));
    }
}
