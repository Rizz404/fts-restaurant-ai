<?php

namespace App\Modules\Knowledge\Enums;

enum KnowledgeCategory: string
{
    case General = 'general';
    case Hours = 'hours';
    case Location = 'location';
    case Policy = 'policy';
    case Faq = 'faq';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return __('info.categories.'.$this->value);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $category) => [$category->value => $category->label()])->all();
    }
}
