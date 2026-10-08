<?php

namespace App\Modules\Identity\Enums;

/**
 * Staff teams that receive guest requests (reservation, feedback and
 * special requests go to front of house, orders go to the kitchen).
 */
enum StaffTeamCode: string
{
    case FrontOfHouse = 'front_of_house';
    case Kitchen = 'kitchen';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return __('ui.teams.'.$this->value);
    }
}
