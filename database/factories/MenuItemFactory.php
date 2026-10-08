<?php

namespace Database\Factories;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\User;
use App\Modules\Menu\Enums\MenuStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Creates the dish shell only; use the Menu actions (or MenuDemoSeeder) for
 * translations, allergens, and publication.
 *
 * @extends Factory<MenuItem>
 */
class MenuItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => Str::slug(fake()->unique()->words(3, true)).'-'.Str::lower(Str::random(4)),
            'category_id' => MenuCategory::query()->firstOrCreate(['code' => 'mains'], ['sort_order' => 1, 'is_active' => true])->id,
            'status' => MenuStatus::Draft,
            'price' => fake()->numberBetween(10, 90) * 1000,
            'currency' => config('restaurant.currency'),
            'is_available' => true,
            'is_chef_pick' => false,
            'sort_order' => 0,
            'created_by' => User::factory(),
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => MenuStatus::Published, 'first_published_at' => now()]);
    }

    public function verified(): static
    {
        return $this->state(fn () => ['allergen_info_verified_at' => now()]);
    }
}
