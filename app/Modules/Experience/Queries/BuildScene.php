<?php

namespace App\Modules\Experience\Queries;

use App\Modules\Experience\Data\SceneData;
use App\Modules\Experience\Enums\SceneCode;
use App\Modules\Experience\Support\ContactLinks;
use App\Modules\Experience\Support\RestaurantSettings;
use App\Modules\Experience\Support\SceneImages;
use App\Modules\Identity\Guest\GuestSessions;

/**
 * Read-only: composes a slide from translations, restaurant settings
 * (loaded once per request), and the image resolver. No cross-guest
 * caching: the table label and context are per request.
 */
final class BuildScene
{
    public function __construct(
        private readonly RestaurantSettings $settings,
        private readonly SceneImages $images,
    ) {}

    /**
     * @param  array{menu_item_id?: ?int, request_public_id?: ?string}  $context  Already authorized by the caller.
     */
    public function handle(SceneCode $scene, array $context = []): SceneData
    {
        $locale = app()->getLocale();
        $key = 'deck.slides.'.$scene->value;
        $settings = $this->settings->get();

        $intro = $scene === SceneCode::Welcome
            ? ($settings->welcomeFor($locale) ?? __($key.'.intro'))
            : __($key.'.intro');

        $topics = __($key.'.topics');
        $label = request()->hasSession() ? request()->session()->get(GuestSessions::TABLE_LABEL_KEY) : null;

        return new SceneData(
            code: $scene,
            locale: $locale,
            title: $scene->label(),
            intro: $intro,
            summary: __($key.'.summary'),
            backdropUrl: $this->images->backdrop($scene),
            characterUrl: $this->images->character($scene),
            avatarUrl: $this->images->avatar(),
            characterName: __('deck.assistant_name'),
            stage: $this->images->layout($scene),
            menu: $this->menu($scene),
            suggestedTopics: is_array($topics) ? array_values($topics) : [],
            context: [
                'scene' => $scene->value,
                'menu_item_id' => $context['menu_item_id'] ?? null,
                'request_public_id' => $context['request_public_id'] ?? null,
            ],
            tableLabel: GuestSessions::isValidTableLabel($label) ? $label : null,
            contactLinks: ContactLinks::build($settings->contact_links),
        );
    }

    /**
     * @return list<array{code: string, label: string, tab: string, href: string, icon: string, image: string, active: bool, accent: string}>
     */
    private function menu(SceneCode $current): array
    {
        return array_map(fn (SceneCode $scene) => [
            'code' => $scene->value,
            'label' => $scene->label(),
            'tab' => $scene->tabLabel(),
            'href' => route($scene->routeName()),
            'icon' => $scene->icon(),
            'image' => $this->images->backdrop($scene),
            'active' => $scene === $current,
            'accent' => $scene->accent(),
        ], SceneCode::cases());
    }
}
