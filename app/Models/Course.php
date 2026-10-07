<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Storage;

#[Fillable(['company_id', 'name', 'description', 'image', 'status', 'sort_order'])]
class Course extends Model
{
    use BelongsToCompany, HasFactory;

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * Public URL for the uploaded cover image, or the raw path/link when the
     * course references an external image.
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

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'course_modules')
            ->withPivot(['status', 'sort_order'])
            ->withTimestamps();
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public function storeProducts(): HasMany
    {
        return $this->hasMany(StoreProduct::class);
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_courses')
            ->withPivot(['status'])
            ->withTimestamps();
    }

    public function timeline(): HasMany
    {
        return $this->hasMany(Timeline::class);
    }

    public function schedules(): MorphMany
    {
        return $this->morphMany(Schedule::class, 'schedulable');
    }

    protected static function booted(): void
    {
        static::deleting(fn (Course $course) => $course->schedules()->delete());
    }
}
