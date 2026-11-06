<?php

namespace App\Modules\Experience\Support;

use App\Modules\Experience\Enums\SceneCode;

/**
 * Builds scene and character image URLs from config/restaurant.php.
 *
 * Local mode serves the stage artwork in public/assets/stage: a background per
 * scene (scenes/{code}) and a cut-out of the host per pose (character/host-{pose}).
 * Placeholder URLs (local development only) use a fixed seed per image so they
 * do not change between requests, and never include user, query, or session
 * data. Production always uses the local assets.
 */
final class SceneImages
{
    public const SOURCE_PLACEHOLDER = 'placeholder';

    public const SOURCE_LOCAL = 'local';

    public function backdrop(SceneCode $scene): string
    {
        return $this->url('scenes/'.$scene->value, $scene->value, 1600, 900);
    }

    /**
     * The host cut-out for a scene's pose (the greeting pose without a scene).
     */
    public function character(?SceneCode $scene = null): string
    {
        $pose = $scene === null ? 'greeting' : (string) ($this->layout($scene)['pose'] ?? 'greeting');

        return $this->url('character/host-'.$pose, 'assistant', 600, 800);
    }

    /**
     * The small round face used beside narration and chat.
     */
    public function avatar(): string
    {
        return $this->url('character/host-avatar', 'avatar', 256, 256);
    }

    /**
     * Where the host stands and how the background is framed for a scene.
     *
     * @return array{pose: string, anchor: string, focus: string, focusMobile: string}
     */
    public function layout(SceneCode $scene): array
    {
        $layout = (array) config('restaurant.scenes.stage.'.$scene->value, []);

        return $layout + ['pose' => 'greeting', 'anchor' => 'right', 'focus' => 'center 50%', 'focusMobile' => '50% 50%'];
    }

    public function source(): string
    {
        if (app()->isProduction()) {
            return self::SOURCE_LOCAL;
        }

        return config('restaurant.scenes.image_source') === self::SOURCE_PLACEHOLDER ? self::SOURCE_PLACEHOLDER : self::SOURCE_LOCAL;
    }

    private function url(string $localName, string $seedName, int $width, int $height): string
    {
        if ($this->source() === self::SOURCE_LOCAL) {
            $path = trim(config('restaurant.scenes.asset_path'), '/');
            $extension = ltrim((string) config('restaurant.scenes.local_extension', 'svg'), '.');

            return asset("{$path}/{$localName}.{$extension}");
        }

        $base = rtrim((string) config('restaurant.scenes.placeholder_base_url'), '/');

        return "{$base}/seed/restaurant-{$seedName}/{$width}/{$height}";
    }
}
