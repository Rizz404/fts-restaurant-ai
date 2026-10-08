<?php

namespace App\Modules\Experience\Data;

use App\Modules\Experience\Enums\SceneCode;

/**
 * Everything a deck page needs to render a slide. Built server-side by
 * BuildScene; views and JS never compose image URLs, menus, or context.
 */
final class SceneData
{
    /**
     * @param  list<array{code: string, label: string, tab: string, href: string, icon: string, image: string, active: bool, accent: string}>  $menu
     * @param  list<string>  $suggestedTopics
     * @param  array{scene: string, menu_item_id: ?int, request_public_id: ?string}  $context
     * @param  list<array{type: string, label: string, href: string, display: string}>  $contactLinks
     */
    public function __construct(
        public readonly SceneCode $code,
        public readonly string $locale,
        public readonly string $title,
        public readonly string $intro,
        public readonly string $summary,
        public readonly string $backdropUrl,
        public readonly string $characterUrl,
        public readonly string $characterName,
        public readonly array $menu,
        public readonly array $suggestedTopics,
        public readonly array $context,
        public readonly ?string $tableLabel,
        public readonly array $contactLinks,
    ) {}

    /**
     * BCP 47 tag for speech synthesis.
     */
    public function speechLang(): string
    {
        return match ($this->locale) {
            'en' => 'en-US',
            'ja' => 'ja-JP',
            default => 'id-ID',
        };
    }
}
