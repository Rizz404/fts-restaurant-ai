<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Restaurant-wide settings (singleton row id = 1): branding, welcome text,
 * contact links, opening hours, and reservation limits. Text only: no HTML,
 * remote URLs, or scripts. Palette stays in theme.css; assets and timezone
 * in config. Null limits fall back to config/restaurant.php.
 */
#[Fillable([
    'brand_name', 'welcome_text', 'contact_links', 'opening_hours',
    'slot_capacity', 'max_party_size', 'min_lead_minutes', 'max_advance_days', 'updated_by',
])]
class RestaurantSetting extends Model
{
    public const SINGLETON_ID = 1;

    public const WEEKDAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    protected function casts(): array
    {
        return [
            'welcome_text' => 'array',
            'contact_links' => 'array',
            'opening_hours' => 'array',
            'slot_capacity' => 'integer',
            'max_party_size' => 'integer',
            'min_lead_minutes' => 'integer',
            'max_advance_days' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function welcomeFor(string $locale): ?string
    {
        $text = $this->welcome_text[$locale] ?? null;

        return is_string($text) && trim($text) !== '' ? $text : null;
    }

    /**
     * Opening hours per weekday (mon..sun), each a list of {open, close} in
     * the restaurant timezone. Missing days are closed.
     *
     * @return array<string, list<array{open: string, close: string}>>
     */
    public function openingHours(): array
    {
        $hours = is_array($this->opening_hours) ? $this->opening_hours : config('restaurant.default_opening_hours');

        $result = [];
        foreach (self::WEEKDAYS as $day) {
            $result[$day] = array_values(array_filter(
                (array) ($hours[$day] ?? []),
                fn ($range) => is_array($range) && isset($range['open'], $range['close']),
            ));
        }

        return $result;
    }

    public function slotCapacity(): int
    {
        return $this->slot_capacity ?? (int) config('restaurant.reservation.slot_capacity');
    }

    public function maxPartySize(): int
    {
        return $this->max_party_size ?? (int) config('restaurant.reservation.max_party_size');
    }

    public function minLeadMinutes(): int
    {
        return $this->min_lead_minutes ?? (int) config('restaurant.reservation.min_lead_minutes');
    }

    public function maxAdvanceDays(): int
    {
        return $this->max_advance_days ?? (int) config('restaurant.reservation.max_advance_days');
    }

    /**
     * @return array{brand_name: string, welcome_text: array<string, string>}
     */
    public static function defaults(): array
    {
        return [
            'brand_name' => 'FTS Restaurant AI',
            'welcome_text' => [
                'id' => 'Selamat datang! Saya host AI restoran ini. Jelajahi menu, tanyakan hidangan, lalu ajukan reservasi atau pesanan dengan mudah.',
                'en' => 'Welcome! I am the restaurant\'s AI host. Browse the menu, ask about dishes, and request a reservation or an order with ease.',
                'ja' => 'ようこそ。当店の AI ホストです。メニューを見たり料理について質問したり、予約や注文もお手伝いします。',
            ],
        ];
    }
}
