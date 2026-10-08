<?php

namespace App\Modules\Identity\Guest;

use Illuminate\Http\Request;

/**
 * Anti-abuse check for guest write endpoints. The default implementation is
 * the honeypot field; a third-party CAPTCHA can be bound to this interface
 * without touching the actions (open decision, see PLAN_05 and PLAN_08).
 */
interface GuestChallenge
{
    /**
     * Whether the request looks like a human submission.
     */
    public function passes(Request $request): bool;
}
