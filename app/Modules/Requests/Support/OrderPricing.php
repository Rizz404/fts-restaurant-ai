<?php

namespace App\Modules\Requests\Support;

use App\Models\MenuItem;
use App\Modules\Menu\Support\Money;

/**
 * Prices an order from the database: unit prices come from the dish rows at
 * the moment of calculation, never from the client or the model. At
 * confirmation the result is frozen into guest_requests.pricing.
 */
final class OrderPricing
{
    /**
     * @param  list<array{menu_item_id: int, quantity: int, note?: ?string}>  $items
     * @return array{lines: list<array<string, mixed>>, total: int, currency: string, unavailable: list<int>}
     *                                                                                                        `unavailable` lists dish IDs that are not published or sold out; their lines are omitted.
     */
    public static function price(array $items, ?string $locale = null): array
    {
        $locale ??= app()->getLocale();
        $ids = array_values(array_unique(array_map(fn (array $item) => (int) $item['menu_item_id'], $items)));

        $dishes = MenuItem::query()
            ->published()
            ->whereIn('menu_items.id', $ids)
            ->with('translations')
            ->get()
            ->keyBy('id');

        $lines = [];
        $unavailable = [];
        $total = 0;
        $currency = (string) config('restaurant.currency');

        foreach ($items as $item) {
            $dish = $dishes->get((int) $item['menu_item_id']);

            if ($dish === null || ! $dish->is_available) {
                $unavailable[] = (int) $item['menu_item_id'];

                continue;
            }

            $quantity = (int) $item['quantity'];
            $lineTotal = $dish->price * $quantity;
            $total += $lineTotal;
            $currency = $dish->currency;

            $lines[] = [
                'menu_item_id' => $dish->id,
                'name' => $dish->translation($locale)?->name ?? $dish->slug,
                'quantity' => $quantity,
                'unit_price' => $dish->price,
                'line_total' => $lineTotal,
                'note' => isset($item['note']) && $item['note'] !== '' ? (string) $item['note'] : null,
            ];
        }

        return ['lines' => $lines, 'total' => $total, 'currency' => $currency, 'unavailable' => array_values(array_unique($unavailable))];
    }

    public static function formatted(int $amount, string $currency, ?string $locale = null): string
    {
        return Money::format($amount, $currency, $locale);
    }
}
