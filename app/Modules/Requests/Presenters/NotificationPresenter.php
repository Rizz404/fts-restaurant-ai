<?php

namespace App\Modules\Requests\Presenters;

use App\Models\GuestRequest;
use App\Models\User;
use App\Notifications\RequestActivity;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Staff notification bell: turns stored notification rows (public id and
 * event code only) into a label, reference, and path. Requests the staff
 * member can no longer reach are listed without a path.
 */
final class NotificationPresenter
{
    private const LABELS = [
        RequestActivity::CREATED => 'requests.notifications.events.request_created',
        RequestActivity::GUEST_MESSAGE => 'requests.notifications.events.guest_message',
        RequestActivity::GUEST_CANCELLED => 'requests.notifications.events.guest_cancelled',
    ];

    /**
     * @return array{items: list<array<string, mixed>>, unread: int}
     */
    public static function forUser(User $user, int $limit = 15): array
    {
        $rows = $user->notifications()->limit($limit)->get();

        $requests = GuestRequest::query()
            ->whereIn('public_id', $rows->map(fn (DatabaseNotification $row) => (string) ($row->data['request_public_id'] ?? ''))->filter()->unique()->all())
            ->get()
            ->keyBy('public_id');

        return [
            'items' => $rows->map(function (DatabaseNotification $row) use ($requests) {
                $data = $row->data;
                $request = $requests->get((string) ($data['request_public_id'] ?? ''));

                return [
                    'id' => $row->id,
                    'label' => __(self::LABELS[$data['event_code'] ?? ''] ?? 'requests.notifications.events.unknown'),
                    'reference' => $request?->reference,
                    'summary' => $request === null ? null : $request->type->label(),
                    'path' => $request === null ? null : route('admin.requests.show', $request->public_id, absolute: false),
                    'read' => $row->read_at !== null,
                    'created_at' => $row->created_at?->toIso8601String(),
                ];
            })->values()->all(),
            'unread' => $user->unreadNotifications()->count(),
        ];
    }
}
