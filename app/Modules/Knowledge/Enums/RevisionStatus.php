<?php

namespace App\Modules\Knowledge\Enums;

enum RevisionStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    public function label(): string
    {
        return __('info.status.'.$this->value);
    }

    /**
     * Badge tone mapping lives here (domain presenter), not in x-ui.badge.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'warning',
            self::Published => 'success',
            self::Archived => 'neutral',
        };
    }
}
