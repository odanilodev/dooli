<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageStorage
{
    public function store(UploadedFile $image, string $folder, int $companyId): string
    {
        return $image->store("{$folder}/{$companyId}", 'public');
    }

    /**
     * Duplicates a stored image into a new path (or reuses an external URL),
     * so templates that clone another template do not share the same file.
     */
    public function copy(?string $image, string $folder, int $companyId): ?string
    {
        if (empty($image)) {
            return null;
        }

        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            return $image;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($image)) {
            return null;
        }

        $extension = pathinfo($image, PATHINFO_EXTENSION);
        $target = "{$folder}/{$companyId}/".Str::uuid().($extension !== '' ? ".{$extension}" : '');

        $disk->copy($image, $target);

        return $target;
    }

    public function delete(?string $image): void
    {
        if (empty($image) || str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            return;
        }

        Storage::disk('public')->delete($image);
    }
}
