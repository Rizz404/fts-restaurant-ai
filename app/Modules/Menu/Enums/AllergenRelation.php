<?php

namespace App\Modules\Menu\Enums;

/**
 * How a dish relates to an allergen: it is an ingredient, or it may contain
 * traces (shared kitchen or supplier warnings).
 */
enum AllergenRelation: string
{
    case Contains = 'contains';
    case MayContain = 'may_contain';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return __('menu.relation.'.$this->value);
    }
}
