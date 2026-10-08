<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Requests\Presenters\NotificationPresenter;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * In-app notifications of the signed-in staff member (own rows only).
 */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = NotificationPresenter::forUser($request->user());

        return ApiResponse::success($data['items'], ['unread' => $data['unread']]);
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $row = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $row->markAsRead();

        return ApiResponse::success(['id' => $row->id, 'read' => true]);
    }
}
