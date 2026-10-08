<?php

namespace App\Modules\Identity;

use App\Models\User;
use App\Modules\Identity\Enums\RoleCode;
use App\Modules\Identity\Enums\StaffTeamCode;

/**
 * Immutable snapshot of who the staff actor is, read from the database.
 *
 * Identity, roles, and teams never come from request bodies, scene context,
 * or AI tool arguments. Team membership only counts with the staff role and
 * an active team. Guests are not users and have no ActorContext; see
 * Identity\Guest\GuestContext.
 */
final class ActorContext
{
    /**
     * @param  list<string>  $roles
     * @param  list<string>  $teamCodes
     */
    public function __construct(
        public readonly int $userId,
        public readonly bool $isActive,
        public readonly bool $mustChangePassword,
        public readonly array $roles,
        public readonly array $teamCodes,
    ) {}

    /**
     * Loads a fresh snapshot from the database.
     */
    public static function load(User|int $user): self
    {
        $id = $user instanceof User ? $user->getKey() : $user;

        /** @var User $fresh */
        $fresh = User::query()
            ->with([
                'roles:id,code',
                'staffTeams' => fn ($query) => $query->where('staff_teams.is_active', true)->select('staff_teams.id', 'code'),
            ])
            ->findOrFail($id);

        $roles = $fresh->roles->map(fn ($role) => $role->code->value)->values()->all();
        $isStaff = in_array(RoleCode::Staff->value, $roles, true);

        return new self(
            userId: $fresh->id,
            isActive: $fresh->is_active,
            mustChangePassword: $fresh->must_change_password,
            roles: $roles,
            teamCodes: $isStaff ? $fresh->staffTeams->map(fn ($team) => $team->code->value)->values()->all() : [],
        );
    }

    public function hasRole(RoleCode $role): bool
    {
        return $this->isActive && in_array($role->value, $this->roles, true);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(RoleCode::Admin);
    }

    public function isEditor(): bool
    {
        return $this->hasRole(RoleCode::Editor);
    }

    public function isStaff(): bool
    {
        return $this->hasRole(RoleCode::Staff);
    }

    public function isStaffOf(StaffTeamCode $team): bool
    {
        return $this->isStaff() && in_array($team->value, $this->teamCodes, true);
    }

    /**
     * Whether the actor may open the /admin area at all. Each page still
     * checks its own ability.
     */
    public function canAccessAdminArea(): bool
    {
        return $this->isAdmin() || $this->isEditor() || $this->isStaff();
    }
}
