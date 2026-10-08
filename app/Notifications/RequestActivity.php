<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * In-app (database) notification for staff about a request. Stores only the
 * request public id and the event code; never contact details, notes, or
 * message text. Sent synchronously inside the request transaction.
 */
class RequestActivity extends Notification
{
    public const CREATED = 'request_created';

    public const GUEST_MESSAGE = 'guest_message';

    public const GUEST_CANCELLED = 'guest_cancelled';

    public function __construct(
        public readonly string $requestPublicId,
        public readonly string $eventCode,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'request_public_id' => $this->requestPublicId,
            'event_code' => $this->eventCode,
        ];
    }
}
