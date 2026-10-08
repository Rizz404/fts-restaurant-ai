<?php

namespace App\Modules\Menu\Support;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Stores dish photos. The upload is decoded and re-encoded as WebP with GD
 * (max 1600 px on the long side), which drops EXIF/GPS data and any payload
 * appended to the original file. Files live on the "public" disk under
 * menu/{slug}-{hash}.webp. Requires ext-gd with WebP support.
 */
final class MenuPhotos
{
    public const DISK = 'public';

    public const DIRECTORY = 'menu';

    public const MAX_SIDE = 1600;

    public const QUALITY = 82;

    public function store(UploadedFile $file, string $slug): string
    {
        if (! extension_loaded('gd') || ! function_exists('imagewebp')) {
            throw new RuntimeException('The GD extension with WebP support is required to store menu photos.');
        }

        $image = $this->decode($file);
        $image = $this->scale($image);

        ob_start();
        imagewebp($image, null, self::QUALITY);
        $bytes = (string) ob_get_clean();
        unset($image);

        if ($bytes === '') {
            throw ValidationException::withMessages(['photo' => __('menu.errors.photo_invalid')]);
        }

        $path = self::DIRECTORY.'/'.$slug.'-'.substr(hash('sha256', $bytes), 0, 12).'.webp';
        Storage::disk(self::DISK)->put($path, $bytes);

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path !== null && str_starts_with($path, self::DIRECTORY.'/')) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    private function decode(UploadedFile $file): GdImage
    {
        $contents = (string) file_get_contents($file->getRealPath());
        $image = @imagecreatefromstring($contents);

        if (! $image instanceof GdImage) {
            throw ValidationException::withMessages(['photo' => __('menu.errors.photo_invalid')]);
        }

        // JPEG orientation lives in EXIF; apply it before the metadata is dropped.
        if (function_exists('exif_read_data') && in_array($file->getMimeType(), ['image/jpeg', 'image/jpg'], true)) {
            $exif = @exif_read_data($file->getRealPath());
            $rotated = match ((int) ($exif['Orientation'] ?? 1)) {
                3 => imagerotate($image, 180, 0),
                6 => imagerotate($image, -90, 0),
                8 => imagerotate($image, 90, 0),
                default => $image,
            };
            if ($rotated instanceof GdImage) {
                $image = $rotated;
            }
        }

        return $image;
    }

    private function scale(GdImage $image): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $longest = max($width, $height);

        if ($longest <= self::MAX_SIDE) {
            imagepalettetotruecolor($image);
            imagealphablending($image, false);
            imagesavealpha($image, true);

            return $image;
        }

        $ratio = self::MAX_SIDE / $longest;
        $scaled = imagescale($image, (int) round($width * $ratio), (int) round($height * $ratio), IMG_BICUBIC);

        return $scaled instanceof GdImage ? $scaled : $image;
    }
}
