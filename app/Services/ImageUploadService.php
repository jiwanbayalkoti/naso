<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ImageUploadService extends BaseService
{
    /**
     * @var array<int, string>
     */
    protected array $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    public function storeImage(
        UploadedFile $file,
        string $directory,
        ?string $oldPath = null,
        int $maxKb = 2048,
        string $field = 'image'
    ): string {
        if (! $file->isValid()) {
            throw ValidationException::withMessages([
                $field => 'The uploaded file is invalid or exceeds the server upload limit.',
            ]);
        }

        $this->ensureDiskSpace($field);

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: '');
        if (! in_array($extension, $this->allowedExtensions, true)) {
            throw ValidationException::withMessages([
                $field => 'Only JPG, PNG, GIF, or WEBP images are allowed.',
            ]);
        }

        if ($file->getSize() > ($maxKb * 1024)) {
            throw ValidationException::withMessages([
                $field => 'Image must be smaller than '.round($maxKb / 1024, 1).' MB.',
            ]);
        }

        $this->deleteIfExists($oldPath);

        $filename = Str::uuid()->toString().'.'.$extension;
        $path = $file->storeAs($directory, $filename, 'public');

        if (! $path) {
            throw ValidationException::withMessages([
                $field => 'Could not save image. Free disk space on the server and try again.',
            ]);
        }

        return $path;
    }

    public function deleteIfExists(?string $path): void
    {
        if (! $path || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return;
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    protected function ensureDiskSpace(string $field, int $minimumMb = 50): void
    {
        $freeBytes = @disk_free_space(storage_path('app/public'));

        if ($freeBytes === false) {
            return;
        }

        if ($freeBytes < ($minimumMb * 1024 * 1024)) {
            throw ValidationException::withMessages([
                $field => 'Server disk space is low (less than '.$minimumMb.' MB free). Free space on drive C: and try again.',
            ]);
        }
    }
}
