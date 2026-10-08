<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesGuest;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ConfirmRequestDraftRequest;
use App\Http\Requests\Api\RequestDraftRequest;
use App\Models\RequestDraft;
use App\Modules\Requests\Actions\ConfirmRequestDraft;
use App\Modules\Requests\Actions\PrepareRequestDraft;
use App\Modules\Requests\Actions\UpdateRequestDraft;
use App\Modules\Requests\Presenters\RequestCards;
use App\Modules\Requests\Presenters\RequestPresenter;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Guest request drafts. Drafts of other guest sessions are 404. Confirming is
 * the only way to create a request and is never done by the AI.
 */
class RequestDraftController extends Controller
{
    use ResolvesGuest;

    public function store(RequestDraftRequest $request, PrepareRequestDraft $prepare): JsonResponse
    {
        $draft = $prepare->handle(
            $this->writer($request),
            $request->validated('type'),
            $request->validated('payload'),
            $request->contact(),
            $request->validated('conversation_public_id'),
        );

        return ApiResponse::success(RequestPresenter::draft($draft), status: 201);
    }

    public function show(Request $request, RequestDraft $draft): JsonResponse
    {
        $guest = $this->reader();
        abort_unless($guest !== null && $draft->guest_session_id === $guest->guestSessionId, 404);

        return ApiResponse::success(RequestPresenter::draft($draft));
    }

    public function update(RequestDraftRequest $request, RequestDraft $draft, UpdateRequestDraft $update): JsonResponse
    {
        $draft = $update->handle(
            $this->writer($request),
            $draft,
            (int) $request->validated('expected_revision'),
            $request->validated('payload'),
            $request->contact(),
        );

        return ApiResponse::success(RequestPresenter::draft($draft));
    }

    public function confirm(ConfirmRequestDraftRequest $request, RequestDraft $draft, ConfirmRequestDraft $confirm): JsonResponse
    {
        $result = $confirm->handle(
            $this->writer($request),
            $draft,
            (int) $request->validated('expected_revision'),
            $request->validated('idempotency_key'),
        );

        return ApiResponse::success(
            RequestCards::confirmation($result['request']) + ['request' => RequestPresenter::forGuest($result['request'])],
            status: $result['created'] ? 201 : 200,
        );
    }
}
