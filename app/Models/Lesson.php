<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\Enums\GeneralStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents a lesson (aula). The table is named "lessons" to keep the route,
 * model and table nomenclature aligned.
 */
#[Fillable([
    'company_id', 'name', 'topic_id', 'description', 'content',
    'sort_order', 'status', 'video_id', 'document_id', 'webinar_link', 'webinar_starts_at', 'published_at',
])]
class Lesson extends Model
{
    use BelongsToCompany, HasFactory;

    protected $attributes = ['status' => 'active'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'webinar_starts_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function attachments(): BelongsToMany
    {
        return $this->belongsToMany(Attachment::class, 'lesson_attachments', 'lesson_id', 'attachment_id')
            ->withPivot(['status', 'sort_order'])
            ->withTimestamps();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }

    public function timeline(): HasMany
    {
        return $this->hasMany(Timeline::class);
    }

    public function isPublished(): bool
    {
        return $this->status === GeneralStatus::Active->value
            && ($this->published_at === null || $this->published_at->isPast());
    }
}
