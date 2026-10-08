<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StaffTeam;
use App\Models\User;
use App\Modules\Identity\CurrentActor;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, CurrentActor $actors): View
    {
        $actor = $actors->for($request->user());

        return view('admin.dashboard', [
            'actor' => $actor,
            'stats' => $actor->isAdmin() ? [
                'active_users' => User::query()->active()->count(),
                'inactive_users' => User::query()->where('is_active', false)->count(),
                'teams' => StaffTeam::query()->active()->count(),
            ] : null,
        ]);
    }
}
