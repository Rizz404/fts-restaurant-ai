<?php

namespace Database\Seeders;

use App\Models\RestaurantSetting;
use App\Models\Role;
use App\Models\StaffTeam;
use App\Modules\Identity\Enums\RoleCode;
use App\Modules\Identity\Enums\StaffTeamCode;
use Illuminate\Database\Seeder;

/**
 * Idempotent reference data: roles, staff teams, and the default restaurant
 * settings row. Safe to run on every deploy; never creates staff accounts.
 */
class ReferenceSeeder extends Seeder
{
    public const STAFF_TEAMS = [
        'front_of_house' => 'Front of house',
        'kitchen' => 'Kitchen',
    ];

    public function run(): void
    {
        foreach (RoleCode::cases() as $role) {
            Role::firstOrCreate(['code' => $role->value]);
        }

        foreach (StaffTeamCode::cases() as $team) {
            StaffTeam::firstOrCreate(['code' => $team->value], ['name' => self::STAFF_TEAMS[$team->value], 'is_active' => true]);
        }

        if (! RestaurantSetting::query()->whereKey(RestaurantSetting::SINGLETON_ID)->exists()) {
            $settings = new RestaurantSetting(RestaurantSetting::defaults());
            $settings->id = RestaurantSetting::SINGLETON_ID;
            $settings->save();
        }
    }
}
