<?php

namespace App\Modules\Identity\Enums;

enum RoleCode: string
{
    case Admin = 'admin';
    case Editor = 'editor';
    case Staff = 'staff';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return __('ui.roles.'.$this->value);
    }
}
