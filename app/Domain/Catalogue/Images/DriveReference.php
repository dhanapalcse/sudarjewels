<?php

namespace App\Domain\Catalogue\Images;

/**
 * Extracts a Google Drive file id from a share link or raw id.
 * Only drive.google.com / docs.google.com links are accepted, and the application
 * never fetches the supplied URL itself — it only calls the Drive API with the parsed id.
 */
final class DriveReference
{
    private const ID = '[A-Za-z0-9_-]{20,128}';

    public static function parse(?string $input): ?string
    {
        $input = trim((string) $input);
        if ($input === '') {
            return null;
        }
        if (preg_match('/^'.self::ID.'$/', $input)) {
            return $input;
        }
        $parts = parse_url($input);
        if (! $parts || ($parts['scheme'] ?? '') !== 'https' || ! in_array(strtolower($parts['host'] ?? ''), ['drive.google.com', 'docs.google.com'], true)) {
            return null;
        }
        if (isset($parts['user']) || isset($parts['port'])) {
            return null;
        }
        if (preg_match('#^/file/d/('.self::ID.')(/|$)#', $parts['path'] ?? '', $m)) {
            return $m[1];
        }
        parse_str($parts['query'] ?? '', $query);
        if (isset($query['id']) && is_string($query['id']) && preg_match('/^'.self::ID.'$/', $query['id'])) {
            return $query['id'];
        }

        return null;
    }
}
