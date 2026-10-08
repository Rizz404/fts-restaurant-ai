<?php

namespace App\Modules\Requests\Enums;

/**
 * Request lifecycle: pending → confirmed → completed, or declined,
 * cancelled; a confirmed reservation may become no_show. completed,
 * declined, cancelled, and no_show are terminal.
 */
enum RequestStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Declined = 'declined';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public function isTerminal(): bool
    {
        return ! in_array($this, [self::Pending, self::Confirmed], true);
    }

    /**
     * Pending and confirmed reservations hold seats in their slot.
     */
    public function holdsSeats(): bool
    {
        return ! $this->isTerminal();
    }

    public function label(): string
    {
        return __('requests.status.'.$this->value);
    }

    /**
     * Badge tone mapping lives here (domain presenter), not in x-ui.badge.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'info',
            self::Confirmed => 'success',
            self::Completed => 'neutral',
            self::Declined => 'danger',
            self::Cancelled, self::NoShow => 'warning',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return list<self>
     */
    public static function active(): array
    {
        return [self::Pending, self::Confirmed];
    }
}
