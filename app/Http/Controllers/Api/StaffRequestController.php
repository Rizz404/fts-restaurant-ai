<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StaffRequestRequests;
use App\Models\GuestRequest;
use App\Modules\Identity\ActorContext;
use App\Modules\Requests\Actions\AppendRequestMessage;
use App\Modules\Requests\Actions\AssignRequest;
use App\Modules\Requests\Actions\ReadRequestHistory;
use App\Modules\Requests\Actions\TransitionRequest;
use App\Modules\Requests\Enums\MessageVisibility;
use App\Modules\Requests\Enums\RequestStatus;
use App\Modules\Requests\Presenters\RequestPresenter;
use App\Modules\Requests\Support\RequestAccess;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Staff side of requests (role staff + team membership). Requests of other
 * teams are 404, an admin without a team assignment gets 403 from the route
 * gate. Internal notes are only returned here.
 */
class StaffRequestController extends Controller
{
    public function assign(StaffRequestRequests $request, GuestRequest $guestRequest, AssignRequest $assign): JsonResponse
    {
        $result = $assign->handle(
            $request->user(),
            $guestRequest,
            (int) $request->validated('assignee_id'),
            (int) $request->validated('expected_version'),
            $request->validated('idempotency_key'),
        );

        return ApiResponse::success(RequestPresenter::forStaff($result['result']));
    }

    public function updateStatus(StaffRequestRequests $request, GuestRequest $guestRequest, TransitionRequest $transition): JsonResponse
    {
        $result = $transition->handle(
            $request->user(),
            $guestRequest,
            RequestStatus::from($request->validated('status')),
            (int) $request->validated('expected_version'),
            $request->validated('reason'),
            $request->validated('idempotency_key'),
        );

        return ApiResponse::success(RequestPresenter::forStaff($result['result']));
    }

    public function messages(Request $request, GuestRequest $guestRequest, ReadRequestHistory $history): JsonResponse
    {
        abort_unless(RequestAccess::staffCanHandle(ActorContext::load($request->user()), $guestRequest), 404);
        $afterId = $request->validate(['after_id' => ['nullable', 'integer', 'min:0']])['after_id'] ?? 0;

        $page = $history->handle($guestRequest, (int) $afterId, includeInternal: true);

        return ApiResponse::success(
            $page['messages']->map(fn ($message) => RequestPresenter::message($message, false))->all(),
            ['next_after_id' => $page['next_after_id']],
        );
    }

    public function storeMessage(StaffRequestRequests $request, GuestRequest $guestRequest, AppendRequestMessage $append): JsonResponse
    {
        $result = $append->forStaff(
            $request->user(),
            $guestRequest,
            $request->validated('text'),
            MessageVisibility::from($request->validated('visibility')),
            $request->validated('idempotency_key'),
        );

        return ApiResponse::success(RequestPresenter::message($result['result'], false), status: $result['replayed'] ? 200 : 201);
    }
}
