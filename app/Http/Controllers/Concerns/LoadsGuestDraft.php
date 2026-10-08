<?php

namespace App\Http\Controllers\Concerns;

use App\Models\RequestDraft;
use App\Modules\Identity\Guest\GuestSessions;
use App\Modules\Requests\Enums\RequestType;
use App\Modules\Requests\Presenters\RequestPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Opens ?draft=<uuid> on a guest page: only the owning guest session may
 * load it (404 otherwise, without creating a session); a confirmed draft
 * redirects to its request.
 */
trait LoadsGuestDraft
{
    /**
     * @param  list<RequestType>  $types  Draft types this page can edit.
     * @return array<string, mixed>|RedirectResponse|null The draft view, a redirect, or null without ?draft.
     */
    protected function openDraft(Request $request, array $types): array|RedirectResponse|null
    {
        if (! $request->filled('draft')) {
            return null;
        }

        $id = (string) $request->query('draft');
        abort_unless(Str::isUuid($id), 404);

        $guest = app(GuestSessions::class)->context();
        abort_if($guest === null, 404);

        $draft = RequestDraft::query()
            ->with('consumedRequest')
            ->where('public_id', strtolower($id))
            ->where('guest_session_id', $guest->guestSessionId)
            ->firstOrFail();

        abort_unless(in_array($draft->type, $types, true), 404);

        if ($draft->consumedRequest !== null) {
            return redirect()->route('experience.bookings.show', $draft->consumedRequest->reference);
        }

        return RequestPresenter::draft($draft);
    }
}
