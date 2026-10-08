<?php

namespace App\Modules\Identity\Guest;

use App\Http\Middleware\RejectHoneypot;
use Illuminate\Http\Request;

/**
 * Passes when the hidden honeypot field is empty. Humans never see the
 * field; simple bots fill every input.
 */
final class HoneypotChallenge implements GuestChallenge
{
    public function passes(Request $request): bool
    {
        return ! filled($request->input(RejectHoneypot::FIELD));
    }
}
