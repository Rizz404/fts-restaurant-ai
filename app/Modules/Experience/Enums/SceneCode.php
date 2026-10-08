<?php

namespace App\Modules\Experience\Enums;

/**
 * The seven deck slides (rooms). The same whitelist is used by routes,
 * context validation, and the present_slide tool (Plan 7).
 */
enum SceneCode: string
{
    case Welcome = 'welcome';
    case Menu = 'menu';
    case Picks = 'picks';
    case Reservation = 'reservation';
    case Order = 'order';
    case Bookings = 'bookings';
    case Info = 'info';

    public function routeName(): string
    {
        return 'experience.'.$this->value;
    }

    public function icon(): string
    {
        return match ($this) {
            self::Welcome => 'sparkles',
            self::Menu => 'cake',
            self::Picks => 'star',
            self::Reservation => 'calendar-days',
            self::Order => 'shopping-bag',
            self::Bookings => 'clipboard-document-list',
            self::Info => 'information-circle',
        };
    }

    /**
     * Accent token of the slide (see presentation.css data-accent values).
     */
    public function accent(): string
    {
        return match ($this) {
            self::Welcome, self::Picks, self::Info => 'saffron',
            self::Menu => 'chili',
            self::Reservation, self::Bookings => 'herb',
            self::Order => 'primary',
        };
    }

    public function label(): string
    {
        return __('deck.slides.'.$this->value.'.label');
    }

    /**
     * Short label for the phone tab bar.
     */
    public function tabLabel(): string
    {
        return __('deck.slides.'.$this->value.'.tab');
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Slides other than Welcome, in deck order.
     *
     * @return list<self>
     */
    public static function rooms(): array
    {
        return array_values(array_filter(self::cases(), fn (self $scene) => $scene !== self::Welcome));
    }
}
