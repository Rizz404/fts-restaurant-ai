<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserAccessRequest;
use App\Models\User;
use App\Modules\Identity\Actions\ProvisionUser;
use App\Modules\Identity\Actions\UpdateUserAccess;
use App\Modules\Identity\Enums\RoleCode;
use App\Modules\Identity\Enums\StaffTeamCode;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);

        $search = trim((string) $request->query('q', ''));
        $role = in_array($request->query('role'), RoleCode::values(), true) ? $request->query('role') : null;
        $status = in_array($request->query('status'), ['active', 'inactive'], true) ? $request->query('status') : null;

        $users = User::query()
            ->with(['roles:id,code', 'staffTeams:id,code'])
            ->when($search !== '', function (Builder $query) use ($search) {
                $like = '%'.addcslashes(mb_strtolower($search), '%_\\').'%';
                $query->where(fn (Builder $query) => $query
                    ->whereRaw("lower(name) like ? escape '\\'", [$like])
                    ->orWhereRaw("lower(email) like ? escape '\\'", [$like]));
            })
            ->when($role, fn (Builder $query) => $query->whereHas('roles', fn (Builder $roles) => $roles->where('code', $role)))
            ->when($status, fn (Builder $query) => $query->where('is_active', $status === 'active'))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'filters' => ['q' => $search, 'role' => $role, 'status' => $status],
            'roleOptions' => $this->roleOptions(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('admin.users.create', $this->formOptions());
    }

    public function store(UserAccessRequest $request, ProvisionUser $provision): RedirectResponse
    {
        $provision->handle($request->user(), $request->validated());

        return redirect()->route('admin.users.index')->with('status', __('ui.users.created'));
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);

        $user->load(['roles:id,code', 'staffTeams:id,code']);

        return view('admin.users.edit', ['user' => $user] + $this->formOptions());
    }

    public function update(UserAccessRequest $request, User $user, UpdateUserAccess $update): RedirectResponse
    {
        $update->handle($request->user(), $user, $request->validated());

        return redirect()->route('admin.users.edit', $user)->with('status', __('ui.users.updated'));
    }

    /**
     * @return array<string, string>
     */
    private function roleOptions(): array
    {
        return collect(RoleCode::cases())->mapWithKeys(fn (RoleCode $role) => [$role->value => $role->label()])->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'roleOptions' => $this->roleOptions(),
            'teamOptions' => collect(StaffTeamCode::cases())->mapWithKeys(fn (StaffTeamCode $team) => [$team->value => $team->label()])->all(),
            'localeOptions' => config('restaurant.locale_names'),
        ];
    }
}
