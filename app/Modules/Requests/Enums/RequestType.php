<?php

namespace App\Modules\Requests\Enums;

use App\Modules\Identity\Enums\StaffTeamCode;

/**
 * Kinds of guest request. The type decides which staff team handles it.
 * Fixed in code; not editable from the CMS in v1.
 */
enum RequestType: string
{
    case Reservation = 'reservation';
    case Order = 'order';
    case Feedback = 'feedback';
    case SpecialRequest = 'special_request';

    public function team(): StaffTeamCode
    {
        return match ($this) {
            self::Order => StaffTeamCode::Kitchen,
            default => StaffTeamCode::FrontOfHouse,
        };
    }

    /**
     * Whether name and phone are required to confirm (feedback may be anonymous).
     */
    public function requiresContact(): bool
    {
        return $this !== self::Feedback;
    }

    public function label(): string
    {
        return __('requests.types.'.$this->value);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
