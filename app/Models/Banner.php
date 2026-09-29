<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'company_id', 'name', 'sort_order', 'path', 'link', 'status',
    'eyebrow', 'title', 'title_highlight', 'description', 'quote',
    'cta_label', 'cta_link', 'secondary_label', 'secondary_link', 'image',
])]
class Banner extends Model
{
    use BelongsToCompany, HasFactory;

    protected $attributes = ['status' => 'active'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * Public URL for the uploaded hero image, or the raw path/link when the
     * banner references an external image.
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
