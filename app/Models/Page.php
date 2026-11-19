<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'needs_review' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saved(fn (Page $page) => cache()->forget('page:'.$page->slug));
    }

    public static function findBySlug(string $slug): ?self
    {
        return cache()->remember('page:'.$slug, 3600, fn () => static::where('slug', $slug)->where('is_published', true)->first());
    }
}
