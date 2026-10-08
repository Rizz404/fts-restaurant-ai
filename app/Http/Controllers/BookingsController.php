<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\LoadsGuestDraft;
use App\Models\GuestRequest;
use App\Modules\Experience\Enums\SceneCode;
use App\Modules\Identity\Guest\GuestSessions;
use App\Modules\Requests\Enums\RequestType;
use App\Modules\Requests\Presenters\RequestPresenter;
use App\Modules\Requests\Support\RequestAccess;
use App\Modules\Requests\Support\RequestReference;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * My Bookings: requests of this guest session, a lookup by reference +
 * contact, and the feedback / special request forms. A reference page only
 * shows a request to its owner or a session that opened it with the contact;
 * everyone else gets the lookup form (not a 404 that would reveal whether a
 * reference exists).
 */
class BookingsController extends ExperienceController
{
    use LoadsGuestDraft;

    public function index(Request $request, GuestSessions $guests): View|RedirectResponse
    {
        $draft = $this->openDraft($request, [RequestType::Feedback, RequestType::SpecialRequest]);
        if ($draft instanceof RedirectResponse) {
            return $draft;
        }

        $guest = $guests->context();
        $requests = $guest === null
            ? collect()
            : RequestAccess::visibleToGuest($guest)->orderByDesc('updated_at')->orderByDesc('id')->limit(50)->get();

        return $this->scene(SceneCode::Bookings, 'experience.bookings', [
            'requests' => $requests->map(fn (GuestRequest $item) => RequestPresenter::forGuest($item))->all(),
            'wizard' => [
                'type' => $draft['type'] ?? RequestType::Feedback->value,
                'initialDraft' => $draft,
                'types' => [RequestType::Feedback->value, RequestType::SpecialRequest->value],
            ],
        ]);
    }

    public function show(string $reference, GuestSessions $guests): View
    {
        $reference = RequestReference::normalize($reference);
        $guest = $guests->context();

        $request = RequestReference::isValid($reference)
            ? GuestRequest::query()->with(['assignee:id,name'])->where('reference', $reference)->first()
            : null;

        $allowed = $request !== null && $guest !== null && RequestAccess::guestMayAct($guest, $request);

        return $this->scene(
            SceneCode::Bookings,
            'experience.bookings-show',
            [
                'reference' => RequestReference::isValid($reference) ? $reference : '',
                'booking' => $allowed ? RequestPresenter::forGuest($request) : null,
            ],
            ['request_public_id' => $allowed ? $request->public_id : null],
        );
    }
}
