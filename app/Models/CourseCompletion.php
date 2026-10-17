<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable record of a student finishing a course. Independent from the
 * certificate document: it survives template changes, lesson edits and even
 * certificate revocation.
 */
#[Fillable([
    'company_id', 'user_id', 'team_id', 'course_id',
    'required_lessons', 'lesson_ids', 'completed_at',
])]
class CourseCompletion extends Model
{
    use BelongsToCompany, HasFactory;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'required_lessons' => 'integer',
            'lesson_ids' => 'array',
            'completed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
