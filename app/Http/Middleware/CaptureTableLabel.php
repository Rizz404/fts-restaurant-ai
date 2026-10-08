<?php

namespace App\Http\Middleware;

use App\Modules\Identity\Guest\GuestSessions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stores the ?table= label from a QR link in the server session on GET page
 * requests. The label is a note on requests only, never authorization;
 * invalid values are ignored. Page views never create a guest session row.
 */
class CaptureTableLabel
{
    public function __construct(private readonly GuestSessions $guests) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && $request->hasSession() && $request->query->has('table')) {
            $label = $request->query('table');

            if (GuestSessions::isValidTableLabel($label)) {
                $request->session()->put(GuestSessions::TABLE_LABEL_KEY, $label);

                $row = $this->guests->current();
                if ($row !== null && $row->table_label !== $label) {
                    $row->forceFill(['table_label' => $label])->save();
                }
            }
        }

        return $next($request);
    }
}
