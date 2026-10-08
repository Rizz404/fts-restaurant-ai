<?php

namespace App\Http\Requests\Admin;

use App\Models\RestaurantSetting;
use App\Modules\Experience\Support\ContactLinks;
use App\Modules\Experience\Support\RestaurantSettings;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Plain text only: no HTML tags, URLs, or script-like content, except the
 * whitelisted contact links (validated and rebuilt by ContactLinks) and the
 * reservation limits and opening hours.
 */
class RestaurantSettingsRequest extends FormRequest
{
    private const NO_MARKUP = 'not_regex:/[<>]|javascript:|https?:\/\/|www\./i';

    public function authorize(): bool
    {
        return $this->user()->can('update', app(RestaurantSettings::class)->get());
    }

    protected function prepareForValidation(): void
    {
        $links = (array) $this->input('contact_links', []);
        foreach (ContactLinks::KEYS as $key) {
            $links[$key] = is_string($links[$key] ?? null) ? trim($links[$key]) : null;
        }

        $this->merge(['contact_links' => $links]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'brand_name' => ['required', 'string', 'min:1', 'max:160', self::NO_MARKUP],
            'welcome_text' => ['required', 'array'],
            'contact_links' => ['array'],
            'contact_links.phone' => ['nullable', 'string', 'max:30', $this->contactRule(ContactLinks::isValidPhone(...))],
            'contact_links.whatsapp' => ['nullable', 'string', 'max:20', $this->contactRule(ContactLinks::isValidWhatsApp(...))],
            'contact_links.instagram' => ['nullable', 'string', 'max:30', $this->contactRule(ContactLinks::isValidInstagram(...))],
            'contact_links.maps_url' => ['nullable', 'string', 'max:300', $this->contactRule(ContactLinks::isValidMapsUrl(...))],
            'slot_capacity' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'max_party_size' => ['nullable', 'integer', 'min:1', 'max:100'],
            'min_lead_minutes' => ['nullable', 'integer', 'min:0', 'max:10080'],
            'max_advance_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'opening_hours' => ['nullable', 'array'],
            'closed_dates' => ['nullable', 'string', 'max:5000', self::NO_MARKUP],
        ];

        foreach (config('restaurant.locales') as $locale) {
            $rules["welcome_text.{$locale}"] = ['nullable', 'string', 'max:1000', self::NO_MARKUP];
        }

        foreach (RestaurantSetting::WEEKDAYS as $day) {
            $rules["opening_hours.{$day}.open"] = ['nullable', 'date_format:H:i'];
            $rules["opening_hours.{$day}.close"] = ['nullable', 'date_format:H:i'];
        }

        return $rules;
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->closedDates() === null) {
                    $validator->errors()->add('closed_dates', __('validation.date_format', ['attribute' => __('deck.settings.closed_dates'), 'format' => 'YYYY-MM-DD']));
                }

                foreach (RestaurantSetting::WEEKDAYS as $day) {
                    $open = $this->input("opening_hours.{$day}.open");
                    $close = $this->input("opening_hours.{$day}.close");

                    if (filled($open) xor filled($close)) {
                        $validator->errors()->add("opening_hours.{$day}.close", __('deck.settings.hours_invalid'));
                    } elseif (filled($open) && filled($close) && $close <= $open) {
                        $validator->errors()->add("opening_hours.{$day}.close", __('deck.settings.hours_invalid'));
                    }
                }
            },
        ];
    }

    /**
     * Closed dates parsed from the textarea (one "YYYY-MM-DD [reason]" per
     * line), or null when any line is malformed.
     *
     * @return list<array{date: string, reason: ?string}>|null
     */
    public function closedDates(): ?array
    {
        $result = [];

        foreach (preg_split('/\R/', (string) $this->input('closed_dates', '')) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (preg_match('/\A(\d{4}-\d{2}-\d{2})(?:\s+(.{1,200}))?\z/u', $line, $match) !== 1) {
                return null;
            }

            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $match[1]);
            if ($date === false || $date->format('Y-m-d') !== $match[1]) {
                return null;
            }

            $result[$match[1]] = ['date' => $match[1], 'reason' => isset($match[2]) ? trim($match[2]) : null];
        }

        return array_values($result);
    }

    public function attributes(): array
    {
        $attributes = [
            'brand_name' => __('deck.settings.brand_name'),
            'contact_links.phone' => __('deck.settings.phone'),
            'contact_links.whatsapp' => __('deck.settings.whatsapp'),
            'contact_links.instagram' => __('deck.settings.instagram'),
            'contact_links.maps_url' => __('deck.settings.maps_url'),
            'slot_capacity' => __('deck.settings.slot_capacity'),
            'max_party_size' => __('deck.settings.max_party_size'),
            'min_lead_minutes' => __('deck.settings.min_lead_minutes'),
            'max_advance_days' => __('deck.settings.max_advance_days'),
            'closed_dates' => __('deck.settings.closed_dates'),
        ];

        foreach (config('restaurant.locale_names') as $locale => $name) {
            $attributes["welcome_text.{$locale}"] = __('deck.settings.welcome_text').' ('.$name.')';
        }

        foreach (RestaurantSetting::WEEKDAYS as $day) {
            $attributes["opening_hours.{$day}.open"] = __('deck.settings.days.'.$day).' – '.__('deck.settings.open');
            $attributes["opening_hours.{$day}.close"] = __('deck.settings.days.'.$day).' – '.__('deck.settings.close');
        }

        return $attributes;
    }

    /**
     * @param  callable(string): bool  $check
     */
    private function contactRule(callable $check): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($check): void {
            if (is_string($value) && $value !== '' && ! $check($value)) {
                $fail(__('validation.regex', ['attribute' => $attribute]));
            }
        };
    }
}
