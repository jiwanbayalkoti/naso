<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves files from storage/app/public when public/storage symlink is missing
 * (common on shared hosting where artisan storage:link is blocked).
 */
class PublicMediaController extends Controller
{
    public function show(Request $request, string $path): BinaryFileResponse
    {
        $path = str_replace('\\', '/', $path);
        $path = ltrim($path, '/');

        if ($path === '' || str_contains($path, '..')) {
            abort(404);
        }

        $fullPath = storage_path('app/public/'.$path);

        if (! is_file($fullPath)) {
            abort(404);
        }

        $realBase = realpath(storage_path('app/public'));
        $realFile = realpath($fullPath);

        if (! $realBase || ! $realFile || ! str_starts_with($realFile, $realBase)) {
            abort(404);
        }

        return response()->file($realFile, [
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }
}
