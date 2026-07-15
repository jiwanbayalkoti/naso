<?php

namespace App\Helpers;

class MediaUrlHelper
{
    /**
     * Absolute URL for a file stored on the public disk.
     *
     * Prefer the /media/{path} app route so images work on live hosts
     * even when `php artisan storage:link` was never run.
     */
    public static function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return self::rewriteLocalhostIfNeeded($path);
        }

        $path = ltrim(str_replace('\\', '/', $path), '/');

        // Stored paths sometimes already include "storage/"
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        return url('media/'.$path);
    }

    /**
     * If APP_URL was wrong at upload time, older DB rows may still point at localhost.
     * Rewrite those to the current app URL when serving pages from live.
     */
    protected static function rewriteLocalhostIfNeeded(string $url): string
    {
        $parsed = parse_url($url);
        $host = strtolower($parsed['host'] ?? '');

        if (! in_array($host, ['localhost', '127.0.0.1', '0.0.0.0'], true)) {
            return $url;
        }

        $path = $parsed['path'] ?? '';
        if (preg_match('#/storage/(.+)$#', $path, $matches)) {
            return url('media/'.$matches[1]);
        }

        if (preg_match('#/media/(.+)$#', $path, $matches)) {
            return url('media/'.$matches[1]);
        }

        return $url;
    }
}
