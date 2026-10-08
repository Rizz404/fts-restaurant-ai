<?php

namespace App\Support\Ui;

use InvalidArgumentException;

/**
 * Single mapping of component variants to full, literal Tailwind classes.
 *
 * Classes must stay literal so Tailwind can find them at build time. Never
 * build color class names from data. Unknown variants throw in local/testing
 * and fall back to the default elsewhere.
 */
final class ComponentStyles
{
    public const BUTTON_BASE = 'inline-flex items-center justify-center gap-2 rounded-control font-medium whitespace-nowrap transition-colors duration-150 motion-reduce:transition-none focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2 focus-visible:ring-offset-surface disabled:cursor-not-allowed disabled:opacity-60 aria-disabled:pointer-events-none aria-disabled:opacity-60';

    public const BUTTON_VARIANTS = [
        'primary' => 'bg-primary text-on-primary hover:bg-primary-hover',
        'secondary' => 'bg-secondary text-on-primary hover:bg-secondary-hover',
        'outline' => 'border border-border bg-surface text-text hover:bg-surface-muted',
        'ghost' => 'bg-transparent text-text hover:bg-surface-muted',
        'danger' => 'bg-danger text-on-primary hover:bg-danger-hover',
        'ai' => 'rounded-full! bg-linear-to-br from-primary to-saffron text-on-primary hover:shadow-glow hover:brightness-110',
        'glass' => 'rounded-full! border border-border bg-surface/80 text-text backdrop-blur-md hover:bg-surface',
    ];

    public const BUTTON_SIZES = [
        'sm' => 'h-8 px-3 text-caption',
        'md' => 'h-10 px-4 text-body',
        'lg' => 'h-12 px-6 text-base',
    ];

    public const BADGE_BASE = 'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-caption font-medium ring-1 ring-inset';

    public const BADGE_TONES = [
        'neutral' => 'bg-surface-muted text-text ring-border',
        'success' => 'bg-success-soft text-success ring-success/30',
        'warning' => 'bg-warning-soft text-warning ring-warning/30',
        'danger' => 'bg-danger-soft text-danger ring-danger/30',
        'info' => 'bg-info-soft text-info ring-info/30',
    ];

    public const ALERT_BASE = 'flex gap-3 rounded-card border p-4 text-body';

    public const ALERT_TONES = [
        'info' => 'border-info/30 bg-info-soft text-text',
        'success' => 'border-success/30 bg-success-soft text-text',
        'warning' => 'border-warning/30 bg-warning-soft text-text',
        'danger' => 'border-danger/30 bg-danger-soft text-text',
    ];

    public const ALERT_ICON_TONES = [
        'info' => 'text-info',
        'success' => 'text-success',
        'warning' => 'text-warning',
        'danger' => 'text-danger',
    ];

    public const ALERT_ICONS = [
        'info' => 'information-circle',
        'success' => 'check-circle',
        'warning' => 'exclamation-triangle',
        'danger' => 'x-circle',
    ];

    public const ICON_SIZES = [
        'sm' => 'size-4',
        'md' => 'size-5',
        'lg' => 'size-6',
    ];

    public const SPINNER_SIZES = [
        'sm' => 'size-4',
        'md' => 'size-5',
        'lg' => 'size-8',
    ];

    public const CARD_BASE = 'rounded-card border border-border bg-surface shadow-card';

    public const CARD_PADDING = [
        'sm' => 'p-4',
        'md' => 'p-4 md:p-6',
    ];

    public const MODAL_SIZES = [
        'sm' => 'max-w-sm',
        'md' => 'max-w-lg',
        'lg' => 'max-w-3xl',
    ];

    public const CONTROL = 'block w-full rounded-control border-border bg-surface text-body text-text shadow-none placeholder:text-muted focus:border-primary focus:ring-2 focus:ring-focus disabled:cursor-not-allowed disabled:bg-surface-muted disabled:text-muted';

    public const CONTROL_INVALID = 'border-danger focus:border-danger focus:ring-danger';

    public const CHECKBOX = 'size-4 rounded border-border text-primary focus:ring-2 focus:ring-focus focus:ring-offset-0 disabled:cursor-not-allowed disabled:opacity-60';

    /**
     * Heroicons (outline) allowed in the UI. Add names here before use.
     */
    public const ICONS = [
        'adjustments-horizontal',
        'arrow-left-start-on-rectangle',
        'arrow-path',
        'arrow-left',
        'arrow-right',
        'arrow-up-right',
        'arrow-uturn-left',
        'bars-3',
        'bell',
        'book-open',
        'building-office-2',
        'cake',
        'calendar-days',
        'chart-bar',
        'chat-bubble-left-right',
        'check',
        'check-badge',
        'check-circle',
        'chevron-down',
        'chevron-left',
        'chevron-right',
        'clipboard-document-list',
        'clock',
        'cog-6-tooth',
        'computer-desktop',
        'document-text',
        'ellipsis-vertical',
        'exclamation-circle',
        'exclamation-triangle',
        'eye',
        'fire',
        'globe-alt',
        'heart',
        'home',
        'inbox',
        'information-circle',
        'language',
        'lifebuoy',
        'magnifying-glass',
        'map-pin',
        'minus',
        'no-symbol',
        'paper-airplane',
        'pause',
        'pencil-square',
        'phone',
        'photo',
        'play',
        'plus',
        'shield-check',
        'shield-exclamation',
        'shopping-bag',
        'sparkles',
        'speaker-wave',
        'speaker-x-mark',
        'squares-2x2',
        'star',
        'tag',
        'ticket',
        'trash',
        'user-circle',
        'users',
        'x-circle',
        'x-mark',
    ];

    public static function button(?string $variant = null, ?string $size = null): string
    {
        return implode(' ', [
            self::BUTTON_BASE,
            self::pick(self::BUTTON_VARIANTS, $variant, 'primary', 'button variant'),
            self::pick(self::BUTTON_SIZES, $size, 'md', 'button size'),
        ]);
    }

    public static function badge(?string $tone = null): string
    {
        return self::BADGE_BASE.' '.self::pick(self::BADGE_TONES, $tone, 'neutral', 'badge tone');
    }

    public static function alert(?string $tone = null): string
    {
        return self::ALERT_BASE.' '.self::pick(self::ALERT_TONES, $tone, 'info', 'alert tone');
    }

    public static function alertIconTone(?string $tone = null): string
    {
        return self::pick(self::ALERT_ICON_TONES, $tone, 'info', 'alert tone');
    }

    public static function alertIcon(?string $tone = null): string
    {
        return self::pick(self::ALERT_ICONS, $tone, 'info', 'alert tone');
    }

    public static function iconSize(?string $size = null): string
    {
        return self::pick(self::ICON_SIZES, $size, 'md', 'icon size');
    }

    public static function spinnerSize(?string $size = null): string
    {
        return self::pick(self::SPINNER_SIZES, $size, 'md', 'spinner size');
    }

    public static function card(?string $padding = null): string
    {
        return self::CARD_BASE.' '.self::pick(self::CARD_PADDING, $padding, 'md', 'card padding');
    }

    public static function modalSize(?string $size = null): string
    {
        return self::pick(self::MODAL_SIZES, $size, 'md', 'modal size');
    }

    public static function control(bool $invalid = false): string
    {
        return $invalid ? self::CONTROL.' '.self::CONTROL_INVALID : self::CONTROL;
    }

    /**
     * Returns the Blade Icons name for a whitelisted Heroicon.
     */
    public static function icon(string $name): string
    {
        if (! in_array($name, self::ICONS, true)) {
            self::unknown('icon', $name);
            $name = 'information-circle';
        }

        return 'heroicon-o-'.$name;
    }

    /**
     * @param  array<string, string>  $map
     */
    private static function pick(array $map, ?string $key, string $default, string $label): string
    {
        if ($key === null || $key === '') {
            return $map[$default];
        }

        if (! array_key_exists($key, $map)) {
            self::unknown($label, $key);

            return $map[$default];
        }

        return $map[$key];
    }

    private static function unknown(string $label, string $value): void
    {
        if (app()->environment(['local', 'testing'])) {
            throw new InvalidArgumentException(sprintf('Unknown %s "%s".', $label, $value));
        }
    }
}
