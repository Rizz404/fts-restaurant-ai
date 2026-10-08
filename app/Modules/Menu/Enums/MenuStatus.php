<?php

namespace App\Modules\Menu\Enums;

enum MenuStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    public function label(): string
    {
        return __('menu.status.'.$this->value);
    }

    /**
     * Badge tone mapping lives here (domain presenter), not in x-ui.badge.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Published => 'success',
            self::Archived => 'warning',
        };
    }
}
