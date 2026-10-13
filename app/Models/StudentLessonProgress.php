<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\Enums\LessonProgressStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id', 'user_id', 'team_id', 'course_id', 'module_id', 'lesson_id',
    'status', 'started_at', 'completed_at', 'position_seconds', 'watched_seconds', 'last_activity_at',
])]
class StudentLessonProgress extends Model
{
    use BelongsToCompany, HasFactory;

    protected $table = 'student_lesson_progress';

    protected $attributes = [
        'status' => 'started',
        'position_seconds' => 0,
        'watched_seconds' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => LessonProgressStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'position_seconds' => 'integer',
            'watched_seconds' => 'integer',
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

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
