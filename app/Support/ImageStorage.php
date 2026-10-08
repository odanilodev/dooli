<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ImageStorage
{
    public function store(UploadedFile $image, string $folder, int $companyId): string
    {
        return $image->store("{$folder}/{$companyId}", 'public');
    }

    public function delete(?string $image): void
    {
        if (empty($image) || str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            return;
        }

        Storage::disk('public')->delete($image);
    }
}
