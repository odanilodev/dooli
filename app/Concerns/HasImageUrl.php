<?php

namespace App\Concerns;

use Illuminate\Support\Facades\Storage;

trait HasImageUrl
{
    /**
     * Public URL for the uploaded image, or the raw path/link when the model
     * references an external image.
     */
    public function imageUrl(): ?string
    {
        if (empty($this->image)) {
            return null;
        }

        if (str_starts_with($this->image, 'http://') || str_starts_with($this->image, 'https://')) {
            return $this->image;
        }

        return Storage::disk('public')->url($this->image);
    }
}
