<?php

namespace App\Models;

use App\Enums\ImageStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['drive_file_id', 'staging_path'];

    protected function casts(): array
    {
        return [
            'status' => ImageStatus::class,
            'renditions' => 'array',
            'is_main' => 'boolean',
            'processed_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function isPublished(): bool
    {
        return ! empty($this->renditions['webp']);
    }

    public static function urlFor(string $path): string
    {
        $cdn = config('sudar.images.cdn_url');

        return $cdn ? rtrim($cdn, '/').'/'.ltrim($path, '/') : Storage::disk(config('sudar.images.disk'))->url($path);
    }

    /** URL of the rendition closest to (and at least) the requested width. */
    public function url(int $width = 640): ?string
    {
        $set = $this->renditions['webp'] ?? [];
        if ($set === []) {
            return null;
        }
        ksort($set);
        foreach ($set as $w => $path) {
            if ((int) $w >= $width) {
                return self::urlFor($path);
            }
        }

        return self::urlFor(end($set));
    }

    public function srcset(): string
    {
        return collect($this->renditions['webp'] ?? [])
            ->map(fn ($path, $w) => self::urlFor($path).' '.$w.'w')
            ->implode(', ');
    }

    /** JPEG rendition used for Open Graph / structured data. */
    public function fallbackUrl(): ?string
    {
        return isset($this->renditions['jpeg']) ? self::urlFor($this->renditions['jpeg']) : $this->url(1400);
    }
}
