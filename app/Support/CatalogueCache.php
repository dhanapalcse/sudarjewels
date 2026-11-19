<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Versioned cache namespace for public catalogue fragments. Bumping the version
 * invalidates all cached catalogue content without needing cache tags.
 */
final class CatalogueCache
{
    public static function key(string $suffix): string
    {
        return 'catalogue:v'.self::version().':'.$suffix;
    }

    public static function remember(string $suffix, int $seconds, \Closure $callback): mixed
    {
        return Cache::remember(self::key($suffix), $seconds, $callback);
    }

    public static function version(): int
    {
        return (int) Cache::get('catalogue:version', 1);
    }

    public static function flush(): void
    {
        Cache::forever('catalogue:version', self::version() + 1);
    }
}
