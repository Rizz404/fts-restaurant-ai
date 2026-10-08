<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GuestRequest;
use App\Models\ReservationSlotUsage;
use App\Models\User;
use App\Modules\Identity\ActorContext;
use App\Modules\Identity\Enums\RoleCode;
use App\Modules\Identity\Enums\StaffTeamCode;
use App\Modules\Requests\Enums\RequestStatus;
use App\Modules\Requests\Enums\RequestType;
use App\Modules\Requests\Presenters\RequestPresenter;
use App\Modules\Requests\Support\OpeningSchedule;
use App\Modules\Requests\Support\RequestAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Staff request panel. Scoped by current team membership, not by the admin
 * role: staff see only requests of the teams they belong to, and a request
 * of another team is a 404.
 */
class RequestController extends Controller
{
    public function index(Request $request, OpeningSchedule $schedule): View
    {
        $actor = ActorContext::load($request->user());
        $teamIds = RequestAccess::handledTeamIds($actor);

        $type = RequestType::tryFrom((string) $request->query('type'));
        $status = RequestStatus::tryFrom((string) $request->query('status'));
        $date = (string) $request->query('date', '');
        $mine = $request->boolean('mine');
        $scope = $request->query('scope', 'active');

        $requests = GuestRequest::query()
            ->with(['assignee:id,name'])
            ->whereIn('team_id', $teamIds)
            ->when($type, fn (Builder $query) => $query->where('type', $type->value))
            ->when($status, fn (Builder $query) => $query->where('status', $status->value))
            ->when(! $status && $scope === 'active', fn (Builder $query) => $query->whereIn('status', array_map(fn (RequestStatus $s) => $s->value, RequestStatus::active())))
            ->when(OpeningSchedule::isDate($date), fn (Builder $query) => $query->whereDate('reserved_date', $date))
            ->when($mine, fn (Builder $query) => $query->where('assignee_id', $request->user()->id))
            ->orderByRaw("case status when 'pending' then 0 when 'confirmed' then 1 else 2 end")
            ->orderBy('reserved_date')
            ->orderBy('reserved_slot')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.requests.index', [
            'requests' => $requests,
            'filters' => ['type' => $type?->value, 'status' => $status?->value, 'date' => $date, 'mine' => $mine, 'scope' => $scope],
            'typeOptions' => collect(RequestType::cases())->mapWithKeys(fn (RequestType $t) => [$t->value => $t->label()])->all(),
            'statusOptions' => collect(RequestStatus::cases())->mapWithKeys(fn (RequestStatus $s) => [$s->value => $s->label()])->all(),
            'today' => $this->today($actor, $teamIds, $schedule),
        ]);
    }

    public function show(Request $request, GuestRequest $guestRequest): View
    {
        $actor = ActorContext::load($request->user());
        abort_unless(RequestAccess::staffCanHandle($actor, $guestRequest), 404);

        $guestRequest->load(['assignee:id,name', 'team:id,code', 'events.actor:id,name']);

        $assignees = User::query()
            ->active()
            ->withRole(RoleCode::Staff)
            ->whereHas('staffTeams', fn (Builder $teams) => $teams->whereKey($guestRequest->team_id)->where('staff_teams.is_active', true))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.requests.show', [
            'guestRequest' => $guestRequest,
            'view' => RequestPresenter::forStaff($guestRequest),
            'assignees' => $assignees,
            'statusActions' => $this->statusActions($guestRequest),
        ]);
    }

    /**
     * Seats used per slot today, for the reservation team ("Hari ini").
     *
     * @param  list<int>  $teamIds
     * @return array{date: string, capacity: int, slots: list<array{slot: string, booked: int}>, pending: int}|null
     */
    private function today(ActorContext $actor, array $teamIds, OpeningSchedule $schedule): ?array
    {
        if (! $actor->isStaffOf(StaffTeamCode::FrontOfHouse)) {
            return null;
        }

        $today = $schedule->today();

        return [
            'date' => $today,
            'capacity' => $schedule->setting()->slotCapacity(),
            'slots' => ReservationSlotUsage::query()->whereDate('date', $today)->where('booked_seats', '>', 0)->orderBy('slot_start')->get()
                ->map(fn (ReservationSlotUsage $usage) => ['slot' => $usage->slot_start, 'booked' => $usage->booked_seats])->all(),
            'pending' => GuestRequest::query()->whereIn('team_id', $teamIds)->where('status', RequestStatus::Pending->value)->count(),
        ];
    }

    /**
     * Status buttons that make sense for the request right now.
     *
     * @return list<array{status: string, label: string, needs_reason: bool, variant: string}>
     */
    private function statusActions(GuestRequest $request): array
    {
        $actions = [];

        if ($request->status === RequestStatus::Pending) {
            $actions[] = ['status' => 'confirmed', 'label' => __('requests.actions.confirm'), 'needs_reason' => false, 'variant' => 'primary'];
            $actions[] = ['status' => 'declined', 'label' => __('requests.actions.decline'), 'needs_reason' => true, 'variant' => 'danger'];
        }

        if ($request->status === RequestStatus::Confirmed) {
            $actions[] = ['status' => 'completed', 'label' => __('requests.actions.complete'), 'needs_reason' => false, 'variant' => 'primary'];
            if ($request->type === RequestType::Reservation) {
                $actions[] = ['status' => 'no_show', 'label' => __('requests.actions.no_show'), 'needs_reason' => false, 'variant' => 'outline'];
            }
        }

        if (! $request->isTerminal()) {
            $actions[] = ['status' => 'cancelled', 'label' => __('requests.actions.cancel'), 'needs_reason' => true, 'variant' => 'outline'];
        }

        return $actions;
    }
}
