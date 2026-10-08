<?php

namespace App\Http\Middleware;

use App\Modules\Experience\Support\RestaurantSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the admin-managed brand name for this request so layouts keep
 * reading config('restaurant.brand.name') without querying the database.
 */
class ApplyRestaurantSettings
{
    public function __construct(private readonly RestaurantSettings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        config(['restaurant.brand.name' => $this->settings->get()->brand_name]);

        return $next($request);
    }
}
