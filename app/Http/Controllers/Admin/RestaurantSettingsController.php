<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RestaurantSettingsRequest;
use App\Models\ClosedDate;
use App\Models\RestaurantSetting;
use App\Modules\Experience\Support\ContactLinks;
use App\Modules\Experience\Support\RestaurantSettings;
use App\Modules\Identity\Actions\WriteAuditEvent;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RestaurantSettingsController extends Controller
{
    public function edit(RestaurantSettings $settings): View
    {
        $current = $settings->get();
        Gate::authorize('update', $current);

        $closed = ClosedDate::query()->orderBy('date')->get()
            ->map(fn (ClosedDate $closed) => trim($closed->date->format('Y-m-d').' '.($closed->reason ?? '')))
            ->implode("\n");

        return view('admin.settings.edit', [
            'settings' => $current,
            'closedDatesText' => $closed,
            'hours' => $current->openingHours(),
        ]);
    }

    public function update(RestaurantSettingsRequest $request, RestaurantSettings $settings, WriteAuditEvent $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $audit) {
            /** @var RestaurantSetting $row */
            $row = RestaurantSetting::query()->lockForUpdate()->find(RestaurantSetting::SINGLETON_ID)
                ?? tap(new RestaurantSetting(RestaurantSetting::defaults()), fn ($row) => $row->id = RestaurantSetting::SINGLETON_ID);

            $welcome = [];
            foreach (config('restaurant.locales') as $locale) {
                $welcome[$locale] = trim((string) $request->validated("welcome_text.{$locale}", ''));
            }

            $links = [];
            foreach (ContactLinks::KEYS as $key) {
                $value = trim((string) $request->validated("contact_links.{$key}", ''));
                if ($value !== '') {
                    $links[$key] = $value;
                }
            }

            $hours = [];
            foreach (RestaurantSetting::WEEKDAYS as $day) {
                $open = $request->validated("opening_hours.{$day}.open");
                $close = $request->validated("opening_hours.{$day}.close");
                $hours[$day] = filled($open) && filled($close) ? [['open' => $open, 'close' => $close]] : [];
            }

            $new = [
                'brand_name' => trim($request->validated('brand_name')),
                'welcome_text' => $welcome,
                'contact_links' => $links === [] ? null : $links,
                'opening_hours' => $hours,
                'slot_capacity' => $request->validated('slot_capacity'),
                'max_party_size' => $request->validated('max_party_size'),
                'min_lead_minutes' => $request->validated('min_lead_minutes'),
                'max_advance_days' => $request->validated('max_advance_days'),
            ];

            $changed = array_keys(array_filter($new, fn ($value, $field) => $row->{$field} !== $value, ARRAY_FILTER_USE_BOTH));

            $closedDates = $request->closedDates() ?? [];
            $existing = ClosedDate::query()->orderBy('date')->get()
                ->map(fn (ClosedDate $closed) => ['date' => $closed->date->format('Y-m-d'), 'reason' => $closed->reason])->all();
            $closedChanged = $existing !== $closedDates;

            $row->fill($new + ['updated_by' => $request->user()->id])->save();

            if ($closedChanged) {
                ClosedDate::query()->delete();
                foreach ($closedDates as $closed) {
                    ClosedDate::query()->create($closed + ['created_by' => $request->user()->id]);
                }
                $changed[] = 'closed_dates';
            }

            if ($changed !== []) {
                // Field names only; no values (welcome text and contacts are plain content anyway).
                $audit->handle($request->user(), 'restaurant_settings.updated', $row, ['fields' => $changed]);
            }
        });

        $settings->forget();

        return redirect()->route('admin.settings.edit')->with('status', __('deck.settings.updated'));
    }
}
