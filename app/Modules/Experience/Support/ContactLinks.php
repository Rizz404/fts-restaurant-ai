<?php

namespace App\Modules\Experience\Support;

/**
 * Whitelisted restaurant contact links. The settings store raw values
 * (phone, whatsapp digits, instagram handle, maps_url); URLs are always
 * built here, never copied from user input except an allow-listed Maps host.
 */
final class ContactLinks
{
    public const KEYS = ['phone', 'whatsapp', 'instagram', 'maps_url'];

    public const MAPS_HOSTS = ['maps.google.com', 'www.google.com', 'maps.app.goo.gl', 'goo.gl'];

    public const PHONE_PATTERN = '/\A\+?[0-9 ]{6,24}\z/';

    public const INSTAGRAM_PATTERN = '/\A[A-Za-z0-9._]{1,30}\z/';

    public static function isValidPhone(string $value): bool
    {
        return preg_match(self::PHONE_PATTERN, $value) === 1 && strlen(preg_replace('/\D/', '', $value)) >= 6;
    }

    public static function isValidWhatsApp(string $value): bool
    {
        return preg_match('/\A[0-9]{6,20}\z/', $value) === 1;
    }

    public static function isValidInstagram(string $value): bool
    {
        return preg_match(self::INSTAGRAM_PATTERN, $value) === 1;
    }

    public static function isValidMapsUrl(string $value): bool
    {
        $parts = parse_url($value);

        return is_array($parts)
            && ($parts['scheme'] ?? null) === 'https'
            && isset($parts['host'])
            && in_array(strtolower($parts['host']), self::MAPS_HOSTS, true)
            && ! isset($parts['user'], $parts['pass'])
            && preg_match('/[\s<>"\']/', $value) === 0;
    }

    /**
     * Builds the display links for the Info slide and JSON-LD from stored values.
     *
     * @param  array<string, mixed>|null  $stored
     * @return list<array{type: string, label: string, href: string, display: string}>
     */
    public static function build(?array $stored): array
    {
        $stored ??= [];
        $links = [];

        $phone = is_string($stored['phone'] ?? null) ? trim($stored['phone']) : '';
        if ($phone !== '' && self::isValidPhone($phone)) {
            $links[] = ['type' => 'phone', 'label' => __('deck.contact.phone'), 'href' => 'tel:'.preg_replace('/[^\d+]/', '', $phone), 'display' => $phone];
        }

        $whatsapp = is_string($stored['whatsapp'] ?? null) ? trim($stored['whatsapp']) : '';
        if ($whatsapp !== '' && self::isValidWhatsApp($whatsapp)) {
            $links[] = ['type' => 'whatsapp', 'label' => __('deck.contact.whatsapp'), 'href' => 'https://wa.me/'.$whatsapp, 'display' => '+'.$whatsapp];
        }

        $instagram = is_string($stored['instagram'] ?? null) ? trim($stored['instagram']) : '';
        if ($instagram !== '' && self::isValidInstagram($instagram)) {
            $links[] = ['type' => 'instagram', 'label' => __('deck.contact.instagram'), 'href' => 'https://www.instagram.com/'.$instagram, 'display' => '@'.$instagram];
        }

        $maps = is_string($stored['maps_url'] ?? null) ? trim($stored['maps_url']) : '';
        if ($maps !== '' && self::isValidMapsUrl($maps)) {
            $links[] = ['type' => 'maps', 'label' => __('deck.contact.maps'), 'href' => $maps, 'display' => __('deck.contact.maps_open')];
        }

        return $links;
    }
}
