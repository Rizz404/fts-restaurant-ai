<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Modules\Identity\Guest\GuestContext;
use App\Modules\Identity\Guest\GuestSessions;
use Illuminate\Http\Request;

/**
 * The guest of a request. Writing endpoints run behind EnsureGuestSession
 * (the row exists); reading endpoints look the session up without ever
 * creating one.
 */
trait ResolvesGuest
{
    protected function writer(Request $request): GuestContext
    {
        $guest = $request->attributes->get('guest');
        abort_unless($guest instanceof GuestContext, 404);

        return $guest;
    }

    protected function reader(): ?GuestContext
    {
        return app(GuestSessions::class)->context();
    }
}
