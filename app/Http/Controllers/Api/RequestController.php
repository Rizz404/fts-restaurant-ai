<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesGuest;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CancelGuestRequestRequest;
use App\Http\Requests\Api\GuestRequestMessageRequest;
use App\Http\Requests\Api\LookupRequestRequest;
use App\Models\GuestRequest;
use App\Modules\Identity\Guest\GuestSessions;
use App\Modules\Requests\Actions\AppendRequestMessage;
use App\Modules\Requests\Actions\CancelRequest;
use App\Modules\Requests\Actions\LookupRequest;
use App\Modules\Requests\Actions\ReadRequestHistory;
use App\Modules\Requests\Presenters\RequestPresenter;
use App\Modules\Requests\Support\RequestAccess;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Guest side of requests: own requests, lookup by reference + contact,
 * public messages, and cancellation. Requests of other guests (including
 * their UUIDs or references) are 404; lookups answer with one uniform error.
 */
class RequestController extends Controller
{
    use ResolvesGuest;

    public function index(): JsonResponse
    {
        $guest = $this->reader();
        if ($guest === null) {
            return ApiResponse::success([], ['next_page' => null]);
        }

        $page = RequestAccess::visibleToGuest($guest)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(20);

        return ApiResponse::success(
            $page->getCollection()->map(fn (GuestRequest $request) => RequestPresenter::forGuest($request))->all(),
            ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        );
    }

    public function show(GuestRequest $guestRequest): JsonResponse
    {
        $this->authorizeGuest($guestRequest);

        return ApiResponse::success(RequestPresenter::forGuest($guestRequest));
    }

    public function lookup(LookupRequestRequest $request, LookupRequest $lookup, GuestSessions $guests): JsonResponse
    {
        $found = $lookup->handle($request->validated('reference'), $request->validated('contact'));

        // A successful lookup is state worth keeping: the grant lives in this browser's guest
        // session, so the row is created now (failed lookups never create one).
        $guests->context(create: true);

        return ApiResponse::success(RequestPresenter::forGuest($found));
    }

    public function messages(Request $request, GuestRequest $guestRequest, ReadRequestHistory $history): JsonResponse
    {
        $this->authorizeGuest($guestRequest);
        $afterId = $request->validate(['after_id' => ['nullable', 'integer', 'min:0']])['after_id'] ?? 0;

        $page = $history->handle($guestRequest, (int) $afterId, includeInternal: false);

        return ApiResponse::success(
            $page['messages']->map(fn ($message) => RequestPresenter::message($message, true))->all(),
            [
                'next_after_id' => $page['next_after_id'],
                'request' => ['status' => $guestRequest->status->value, 'status_label' => $guestRequest->status->label(), 'status_tone' => $guestRequest->status->tone(), 'version' => $guestRequest->version, 'can_cancel' => ! $guestRequest->isTerminal()],
            ],
        );
    }

    public function storeMessage(GuestRequestMessageRequest $request, GuestRequest $guestRequest, AppendRequestMessage $append): JsonResponse
    {
        $result = $append->forGuest($this->writer($request), $guestRequest, $request->validated('text'), $request->validated('idempotency_key'));

        return ApiResponse::success(RequestPresenter::message($result['result'], true), status: $result['replayed'] ? 200 : 201);
    }

    public function cancel(CancelGuestRequestRequest $request, GuestRequest $guestRequest, CancelRequest $cancel): JsonResponse
    {
        $result = $cancel->handle($this->writer($request), $guestRequest, $request->validated('reason'), $request->validated('idempotency_key'));

        return ApiResponse::success(RequestPresenter::forGuest($result['result']));
    }

    private function authorizeGuest(GuestRequest $request): void
    {
        $guest = $this->reader();
        abort_unless($guest !== null && RequestAccess::guestMayAct($guest, $request), 404);
    }
}
