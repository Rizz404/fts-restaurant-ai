<?php

namespace App\Modules\Requests\Support;

use App\Models\GuestRequest;
use App\Models\User;
use App\Modules\Identity\Enums\RoleCode;
use App\Notifications\RequestActivity;
use Illuminate\Support\Collection;

/**
 * Chooses staff recipients and writes database notifications. Guests are
 * never notified here (they poll the status page). Call inside the request
 * transaction. Notification data holds only the request public id and the
 * event code.
 */
final class RequestNotifier
{
    /** New request: every active staff member of the handling team. */
    public static function created(GuestRequest $request): void
    {
        self::send(self::teamStaff($request), $request, RequestActivity::CREATED);
    }

    /** Guest message: the assignee, or the team when nobody is assigned. */
    public static function guestMessage(GuestRequest $request): void
    {
        self::send(self::handlers($request), $request, RequestActivity::GUEST_MESSAGE);
    }

    /** Guest cancellation: the assignee, or the team when nobody is assigned. */
    public static function guestCancelled(GuestRequest $request): void
    {
        self::send(self::handlers($request), $request, RequestActivity::GUEST_CANCELLED);
    }

    /**
     * @return Collection<int, int>
     */
    private static function handlers(GuestRequest $request): Collection
    {
        return $request->assignee_id !== null ? collect([$request->assignee_id]) : self::teamStaff($request);
    }

    /**
     * @return Collection<int, int>
     */
    private static function teamStaff(GuestRequest $request): Collection
    {
        return User::query()
            ->active()
            ->withRole(RoleCode::Staff)
            ->whereHas('staffTeams', fn ($query) => $query->whereKey($request->team_id)->where('staff_teams.is_active', true))
            ->pluck('id');
    }

    /**
     * @param  Collection<int, int|null>  $recipientIds
     */
    private static function send(Collection $recipientIds, GuestRequest $request, string $event): void
    {
        $ids = $recipientIds->filter()->map(fn ($id) => (int) $id)->unique()->values();
        if ($ids->isEmpty()) {
            return;
        }

        $notification = new RequestActivity($request->public_id, $event);
        User::query()->whereIn('id', $ids)->active()->get()->each->notify($notification);
    }
}
