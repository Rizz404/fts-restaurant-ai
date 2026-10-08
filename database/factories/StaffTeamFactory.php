<?php

namespace Database\Factories;

use App\Models\StaffTeam;
use App\Modules\Identity\Enums\StaffTeamCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Staff teams are reference data; prefer ReferenceSeeder.
 *
 * @extends Factory<StaffTeam>
 */
class StaffTeamFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = fake()->randomElement(StaffTeamCode::cases());

        return [
            'code' => $code,
            'name' => $code->value,
            'is_active' => true,
        ];
    }
}
