<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\LoadsGuestDraft;
use App\Modules\Experience\Enums\SceneCode;
use App\Modules\Requests\Enums\RequestType;
use App\Modules\Requests\Support\OpeningSchedule;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Reservation slide: a three-step wizard (date and party size → slot →
 * contact, review, confirm). Works as a plain page; the draft lives on the
 * server and is confirmed only by the guest's button.
 */
class ReservationController extends ExperienceController
{
    use LoadsGuestDraft;

    public function __invoke(Request $request, OpeningSchedule $schedule): View|RedirectResponse
    {
        $draft = $this->openDraft($request, [RequestType::Reservation]);
        if ($draft instanceof RedirectResponse) {
            return $draft;
        }

        $date = $request->query('date');
        $party = $request->integer('party') ?: null;
        $setting = $schedule->setting();

        return $this->scene(SceneCode::Reservation, 'experience.reservation', [
            'wizard' => [
                'type' => RequestType::Reservation->value,
                'initialDraft' => $draft,
                'initialDate' => is_string($date) && OpeningSchedule::isDate($date) ? $date : null,
                'initialParty' => $party !== null && $party <= $setting->maxPartySize() ? $party : null,
                'minDate' => $schedule->today(),
                'maxDate' => $schedule->latestDate(),
                'maxParty' => $setting->maxPartySize(),
            ],
        ]);
    }
}
