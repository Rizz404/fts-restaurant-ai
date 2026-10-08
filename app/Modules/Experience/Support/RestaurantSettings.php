<?php

namespace App\Modules\Experience\Support;

use App\Models\RestaurantSetting;
use Illuminate\Database\QueryException;

/**
 * Loads the restaurant settings row at most once per request (scoped
 * service). Falls back to defaults before migrations/seeding so the app
 * still boots.
 */
final class RestaurantSettings
{
    private ?RestaurantSetting $settings = null;

    public function get(): RestaurantSetting
    {
        if ($this->settings !== null) {
            return $this->settings;
        }

        try {
            $settings = RestaurantSetting::query()->find(RestaurantSetting::SINGLETON_ID);
        } catch (QueryException) {
            $settings = null;
        }

        return $this->settings = $settings ?? new RestaurantSetting(RestaurantSetting::defaults());
    }

    public function forget(): void
    {
        $this->settings = null;
    }
}
