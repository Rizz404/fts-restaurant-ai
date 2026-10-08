<?php

namespace Database\Factories;

use App\Models\Role;
use App\Modules\Identity\Enums\RoleCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Roles are reference data; prefer ReferenceSeeder. Use firstOrCreate in tests.
 *
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->randomElement(RoleCode::cases()),
        ];
    }
}
