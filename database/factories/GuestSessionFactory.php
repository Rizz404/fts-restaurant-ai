<?php

namespace Database\Factories;

use App\Models\GuestSession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GuestSession>
 */
class GuestSessionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $now = now();

        return [
            'public_id' => (string) Str::uuid(),
            'locale' => 'id',
            'table_label' => null,
            'created_at' => $now,
            'last_seen_at' => $now,
            'expires_at' => $now->copy()->addDays((int) config('restaurant.retention.guest_session_days')),
        ];
    }
}
