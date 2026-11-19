<?php

namespace App\Domain\Catalogue\Images;

use App\Enums\ImageStatus;
use App\Models\ProductImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Validates an image binary and publishes optimised WebP renditions (plus one JPEG for
 * Open Graph) to media storage. Existing renditions are only replaced after the new set
 * has been fully written, so a failed re-import never breaks the published image.
 */
class ImageProcessor
{
    public function validate(string $binary): array
    {
        $cfg = config('sudar.images');
        if (strlen($binary) === 0 || strlen($binary) > $cfg['max_bytes']) {
            throw new ImageImportException('Image must be smaller than '.round($cfg['max_bytes'] / 1048576).' MB.');
        }
        $info = @getimagesizefromstring($binary);
        if (! $info || ! in_array($info['mime'], $cfg['allowed_mimes'], true)) {
            throw new ImageImportException('File is not a supported image (JPEG, PNG or WebP).');
        }
        [$w, $h] = $info;
        if ($w < $cfg['min_width'] || $h < $cfg['min_height']) {
            throw new ImageImportException("Image is too small ({$w}×{$h}). Minimum is {$cfg['min_width']}×{$cfg['min_height']}.");
        }
        if ($w > $cfg['max_width'] || $h > $cfg['max_height']) {
            throw new ImageImportException("Image dimensions are too large ({$w}×{$h}).");
        }

        return ['width' => $w, 'height' => $h, 'mime' => $info['mime']];
    }

    public function publish(ProductImage $image, string $binary): void
    {
        $this->validate($binary);
        $cfg = config('sudar.images');
        $disk = Storage::disk($cfg['disk']);
        $manager = new ImageManager(new Driver);

        try {
            $source = $manager->read($binary);
        } catch (\Throwable) {
            throw new ImageImportException('The image could not be decoded.');
        }
        $width = $source->width();
        $height = $source->height();

        $version = Str::lower(Str::random(8));
        $base = "products/{$image->product_id}/{$image->id}-{$version}";
        $written = [];
        $renditions = ['webp' => []];
        try {
            foreach ($cfg['widths'] as $target) {
                if ($target > $width && $renditions['webp'] !== []) {
                    continue;
                }
                $copy = $manager->read($binary)->scaleDown(width: min($target, $width));
                $path = "{$base}-{$target}.webp";
                $disk->put($path, (string) $copy->toWebp(quality: $cfg['quality']), ['visibility' => 'public']);
                $written[] = $path;
                $renditions['webp'][(string) min($target, $width)] = $path;
            }
            $jpeg = $manager->read($binary)->scaleDown(width: min(1200, $width));
            $jpegPath = "{$base}-og.jpg";
            $disk->put($jpegPath, (string) $jpeg->toJpeg(quality: $cfg['quality']), ['visibility' => 'public']);
            $written[] = $jpegPath;
            $renditions['jpeg'] = $jpegPath;
        } catch (\Throwable $e) {
            $disk->delete($written);
            throw $e;
        }

        $old = $image->renditions;
        $image->forceFill([
            'renditions' => $renditions,
            'width' => $width,
            'height' => $height,
            'status' => ImageStatus::Ready,
            'error' => null,
            'processed_at' => now(),
        ])->save();

        if (is_array($old)) {
            $disk->delete(array_values(array_merge(array_values($old['webp'] ?? []), isset($old['jpeg']) ? [$old['jpeg']] : [])));
        }
    }
}
