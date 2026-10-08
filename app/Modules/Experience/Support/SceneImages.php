<?php

namespace App\Modules\Experience\Support;

use App\Modules\Experience\Enums\SceneCode;

/**
 * Builds scene and character image URLs from config/restaurant.php.
 *
 * Placeholder URLs use a fixed seed per image so they do not change between
 * requests, and never include user, query, or session data. Production
 * always uses the local assets (release gate: no remote placeholder images).
 */
final class SceneImages
{
    public const SOURCE_PLACEHOLDER = 'placeholder';

    public const SOURCE_LOCAL = 'local';

    public function backdrop(SceneCode $scene): string
    {
        return $this->url($scene->value, 1600, 900);
    }

    public function character(): string
    {
        return $this->url('assistant', 600, 800);
    }

    public function source(): string
    {
        if (app()->isProduction()) {
            return self::SOURCE_LOCAL;
        }

        return config('restaurant.scenes.image_source') === self::SOURCE_PLACEHOLDER ? self::SOURCE_PLACEHOLDER : self::SOURCE_LOCAL;
    }

    private function url(string $name, int $width, int $height): string
    {
        if ($this->source() === self::SOURCE_LOCAL) {
            $path = trim(config('restaurant.scenes.asset_path'), '/');
            $extension = ltrim((string) config('restaurant.scenes.local_extension', 'svg'), '.');

            return asset("{$path}/{$name}.{$extension}");
        }

        $base = rtrim((string) config('restaurant.scenes.placeholder_base_url'), '/');

        return "{$base}/seed/restaurant-{$name}/{$width}/{$height}";
    }
}
