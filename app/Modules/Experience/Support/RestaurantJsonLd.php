<?php

namespace App\Modules\Experience\Support;

/**
 * schema.org `Restaurant` description for the Welcome and Info pages, built
 * only from the restaurant settings (name, opening hours, whitelisted
 * contact links). Nothing from guests, menu items, or requests is included.
 */
final class RestaurantJsonLd
{
    private const DAYS = [
        'mon' => 'Monday',
        'tue' => 'Tuesday',
        'wed' => 'Wednesday',
        'thu' => 'Thursday',
        'fri' => 'Friday',
        'sat' => 'Saturday',
        'sun' => 'Sunday',
    ];

    public function __construct(private readonly RestaurantSettings $settings) {}

    /**
     * @return array<string, mixed>
     */
    public function build(?string $description = null): array
    {
        $setting = $this->settings->get();
        $links = ContactLinks::build($setting->contact_links);

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Restaurant',
            'name' => $setting->brand_name ?: config('restaurant.brand.name'),
            'url' => url('/'),
            'inLanguage' => config('restaurant.locales'),
        ];

        if ($description !== null && $description !== '') {
            $data['description'] = $description;
        }

        $hours = [];
        foreach ($setting->openingHours() as $day => $ranges) {
            foreach ($ranges as $range) {
                $hours[] = [
                    '@type' => 'OpeningHoursSpecification',
                    'dayOfWeek' => self::DAYS[$day],
                    'opens' => $range['open'],
                    'closes' => $range['close'],
                ];
            }
        }
        if ($hours !== []) {
            $data['openingHoursSpecification'] = $hours;
        }

        foreach ($links as $link) {
            match ($link['type']) {
                'phone' => $data['telephone'] = $link['display'],
                'instagram' => $data['sameAs'][] = $link['href'],
                'maps' => $data['hasMap'] = $link['href'],
                default => null,
            };
        }

        return $data;
    }
}
