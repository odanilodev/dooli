<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'company_id', 'name', 'grade', 'title', 'course_id', 'version', 'time',
    'background', 'signature', 'body', 'signatory_name', 'signatory_title',
    'layout', 'modules_placement', 'modules_title', 'modules_layout', 'modules_background',
])]
class Certificate extends Model
{
    use BelongsToCompany, HasFactory;

    protected function casts(): array
    {
        return [
            'grade' => 'decimal:2',
            'time' => 'integer',
            'layout' => 'array',
            'modules_layout' => 'array',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function certificateUsers(): HasMany
    {
        return $this->hasMany(CertificateUser::class);
    }

    /**
     * Public URL for the uploaded background image, or the raw path/link when
     * the certificate references an external image.
     */
    public function backgroundUrl(): ?string
    {
        return $this->publicUrl($this->background);
    }

    /**
     * Public URL for the uploaded signature image, or the raw path/link when
     * the certificate references an external image.
     */
    public function signatureUrl(): ?string
    {
        return $this->publicUrl($this->signature);
    }

    /**
     * Public URL for the optional background of the course modules page.
     */
    public function modulesBackgroundUrl(): ?string
    {
        return $this->publicUrl($this->modules_background);
    }

    private function publicUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }
}
