<?php

namespace App\Modules\Conversation\Actions;

use App\Models\Conversation;
use App\Modules\Experience\Enums\SceneCode;
use App\Modules\Identity\Guest\GuestContext;
use Illuminate\Support\Str;

class StartConversation
{
    public function handle(GuestContext $guest, string $locale, SceneCode $scene): Conversation
    {
        return Conversation::create([
            'public_id' => (string) Str::uuid(),
            'guest_session_id' => $guest->guestSessionId,
            'locale' => $locale,
            'scene_context' => ['scene' => $scene->value, 'menu_item_id' => null, 'request_public_id' => null],
            'context_revision' => 1,
            'last_activity_at' => now(),
        ]);
    }
}
