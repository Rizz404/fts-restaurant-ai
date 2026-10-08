<?php

namespace App\Modules\Requests\Actions;

use App\Models\GuestRequest;
use App\Models\RequestMessage;
use App\Modules\Requests\Enums\MessageVisibility;
use Illuminate\Support\Collection;

/**
 * Messages after a cursor (id ascending, max 50). Guest views get public
 * messages only; internal notes are added for staff views.
 */
class ReadRequestHistory
{
    public const PAGE_SIZE = 50;

    /**
     * @return array{messages: Collection<int, RequestMessage>, next_after_id: ?int}
     */
    public function handle(GuestRequest $request, int $afterId, bool $includeInternal): array
    {
        $messages = RequestMessage::query()
            ->with('author:id,name')
            ->where('request_id', $request->id)
            ->where('id', '>', $afterId)
            ->when(! $includeInternal, fn ($query) => $query->where('visibility', MessageVisibility::Public->value))
            ->orderBy('id')
            ->limit(self::PAGE_SIZE)
            ->get();

        return [
            'messages' => $messages,
            'next_after_id' => $messages->count() === self::PAGE_SIZE ? $messages->last()->id : null,
        ];
    }
}
