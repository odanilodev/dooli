<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'company_id', 'lesson_id', 'course_id', 'team_id', 'position_seconds', 'duration'])]
class VideoProgress extends Model
{
    use BelongsToCompany, HasFactory;

    protected $table = 'video_progresses';

    protected $attributes = ['position_seconds' => 0];

    protected function casts(): array
    {
        return [
            'position_seconds' => 'integer',
            'duration' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
