<?php

namespace App\Modules\Requests\Support;

use App\Models\GuestRequest;
use App\Models\StaffTeam;
use App\Modules\Identity\ActorContext;
use App\Modules\Identity\Enums\StaffTeamCode;
use App\Modules\Identity\Guest\GuestContext;
use App\Modules\Identity\Guest\SessionGrants;
use Illuminate\Database\Eloquent\Builder;

/**
 * Request read/handle rules (TECHNICAL_CONTRACTS): a guest reads requests of
 * their own session or ones opened with reference + contact (grants stored in
 * the server session); active staff of the request's team handle it,
 * including internal notes. The admin role alone grants nothing.
 */
final class RequestAccess
{
    public static function guestOwns(GuestContext $guest, GuestRequest $request): bool
    {
        return $request->guest_session_id !== null && $request->guest_session_id === $guest->guestSessionId;
    }

    /**
     * Owner, or a request this session opened with reference + contact.
     */
    public static function guestCanView(GuestContext $guest, string $requestPublicId): bool
    {
        $requestPublicId = strtolower($requestPublicId);

        if (app(SessionGrants::class)->has($requestPublicId)) {
            return GuestRequest::query()->where('public_id', $requestPublicId)->exists();
        }

        return GuestRequest::query()
            ->where('public_id', $requestPublicId)
            ->where('guest_session_id', $guest->guestSessionId)
            ->exists();
    }

    public static function guestMayAct(GuestContext $guest, GuestRequest $request): bool
    {
        return self::guestOwns($guest, $request) || app(SessionGrants::class)->has($request->public_id);
    }

    /**
     * Requests a guest may list: own session plus grants.
     *
     * @return Builder<GuestRequest>
     */
    public static function visibleToGuest(GuestContext $guest): Builder
    {
        $grants = app(SessionGrants::class)->all();

        return GuestRequest::query()->where(function (Builder $query) use ($guest, $grants) {
            $query->where('guest_session_id', $guest->guestSessionId);
            if ($grants !== []) {
                $query->orWhereIn('public_id', $grants);
            }
        });
    }

    public static function staffCanHandle(ActorContext $actor, GuestRequest $request): bool
    {
        $request->loadMissing('team:id,code,is_active');
        $team = $request->team;

        return $team !== null
            && $team->is_active
            && $actor->isStaffOf($team->code instanceof StaffTeamCode ? $team->code : StaffTeamCode::from($team->code));
    }

    /**
     * Team ids the actor currently handles as staff.
     *
     * @return list<int>
     */
    public static function handledTeamIds(ActorContext $actor): array
    {
        if (! $actor->isStaff() || $actor->teamCodes === []) {
            return [];
        }

        return StaffTeam::query()
            ->active()
            ->whereIn('code', $actor->teamCodes)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
