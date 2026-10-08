<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\LoadsGuestDraft;
use App\Models\MenuItem;
use App\Modules\Experience\Enums\SceneCode;
use App\Modules\Menu\Support\Money;
use App\Modules\Requests\Enums\RequestType;
use App\Modules\Requests\Support\OpeningSchedule;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Order slide: a cart of available dishes (kept in this browser), then
 * fulfilment, time, contact, review, and confirm. Prices shown here are a
 * preview; the server prices the order from the database at every step and
 * freezes the total at confirmation. Takeaway or dine-in pre-order only.
 */
class OrderController extends ExperienceController
{
    use LoadsGuestDraft;

    public function __invoke(Request $request, OpeningSchedule $schedule): View|RedirectResponse
    {
        $draft = $this->openDraft($request, [RequestType::Order]);
        if ($draft instanceof RedirectResponse) {
            return $draft;
        }

        $dishes = MenuItem::query()
            ->published()
            ->with(['translations', 'category.translations'])
            ->orderBy('category_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (MenuItem $item) {
                $translation = $item->translation();

                return [
                    'id' => $item->id,
                    'name' => $translation?->name ?? $item->slug,
                    'category' => $item->category->name(),
                    'price' => $item->price,
                    'priceFormatted' => Money::format($item->price, $item->currency),
                    'currency' => $item->currency,
                    'available' => $item->is_available,
                ];
            })
            ->all();

        return $this->scene(SceneCode::Order, 'experience.order', [
            'wizard' => [
                'type' => RequestType::Order->value,
                'initialDraft' => $draft,
                'dishes' => $dishes,
                'currency' => config('restaurant.currency'),
                'today' => $schedule->today(),
                'hours' => $schedule->rangesFor($schedule->today()),
                'closedToday' => $schedule->isClosedDate($schedule->today()),
                'leadMinutes' => $schedule->setting()->minLeadMinutes(),
            ],
        ]);
    }
}
