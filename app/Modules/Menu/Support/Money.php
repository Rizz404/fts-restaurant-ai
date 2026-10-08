<?php

namespace App\Modules\Menu\Support;

use NumberFormatter;

/**
 * Formats prices. Amounts are integers in the smallest currency unit; the
 * default currency (IDR) has no decimals. The display format follows the
 * guest's locale (NumberFormatter from ext-intl), never the stored number.
 */
final class Money
{
    private const INTL_LOCALES = ['id' => 'id_ID', 'en' => 'en_US', 'ja' => 'ja_JP'];

    public static function format(int $amount, ?string $currency = null, ?string $locale = null): string
    {
        $currency ??= (string) config('restaurant.currency');
        $locale ??= app()->getLocale();

        if (class_exists(NumberFormatter::class)) {
            $formatter = new NumberFormatter(self::INTL_LOCALES[$locale] ?? 'id_ID', NumberFormatter::CURRENCY);
            $digits = self::fractionDigits($currency);
            $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $digits);
            $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $digits);

            $text = $formatter->formatCurrency($amount / (10 ** $digits), $currency);
            if (is_string($text)) {
                return $text;
            }
        }

        return $currency.' '.number_format($amount, 0, ',', '.');
    }

    /**
     * Number of decimals of the currency's smallest unit (the stored integer
     * is always in that unit).
     */
    public static function fractionDigits(string $currency): int
    {
        return in_array(strtoupper($currency), ['IDR', 'JPY', 'KRW', 'VND'], true) ? 0 : 2;
    }
}
