<?php

namespace App\Repositories;

use App\Enums\LessonProgressStatus;
use App\Models\StudentLessonProgress;
use Illuminate\Support\Facades\DB;

class StudentLessonProgressRepository extends BaseRepository
{
    protected array $filterable = ['user_id', 'team_id', 'course_id', 'module_id', 'lesson_id', 'status', 'company_id'];

    public function __construct()
    {
        parent::__construct(new StudentLessonProgress);
    }

    /**
     * Marks a lesson as started for a student, preserving the first start date.
     */
    public function markStarted(int $userId, int $lessonId, int $courseId, int $teamId, int $moduleId): void
    {
        $now = now();

        $this->query()->updateOrCreate(
            [
                'user_id' => $userId,
                'team_id' => $teamId,
                'course_id' => $courseId,
                'lesson_id' => $lessonId,
            ],
            [
                'module_id' => $moduleId,
                'last_activity_at' => $now,
            ],
        );

        $this->query()
            ->where('user_id', $userId)
            ->where('team_id', $teamId)
            ->where('course_id', $courseId)
            ->where('lesson_id', $lessonId)
            ->whereNull('started_at')
            ->update(['started_at' => $now]);
    }

    /**
     * Marks a lesson as completed for a student.
     */
    public function markCompleted(int $userId, int $lessonId, int $courseId, int $teamId, int $moduleId): void
    {
        $now = now();

        $this->query()->updateOrCreate(
            [
                'user_id' => $userId,
                'team_id' => $teamId,
                'course_id' => $courseId,
                'lesson_id' => $lessonId,
            ],
            [
                'module_id' => $moduleId,
                'status' => LessonProgressStatus::Completed->value,
                'completed_at' => $now,
                'last_activity_at' => $now,
            ],
        );

        $this->query()
            ->where('user_id', $userId)
            ->where('team_id', $teamId)
            ->where('course_id', $courseId)
            ->where('lesson_id', $lessonId)
            ->whereNull('started_at')
            ->update(['started_at' => $now]);
    }

    /**
     * Records the latest watched position, keeping the highest value reached.
     */
    public function markWatched(
        int $userId,
        int $lessonId,
        int $courseId,
        int $teamId,
        int $moduleId,
        int $position,
    ): void {
        $now = now();

        $this->query()->updateOrCreate(
            [
                'user_id' => $userId,
                'team_id' => $teamId,
                'course_id' => $courseId,
                'lesson_id' => $lessonId,
            ],
            [
                'module_id' => $moduleId,
                'last_activity_at' => $now,
            ],
        );

        $this->query()
            ->where('user_id', $userId)
            ->where('team_id', $teamId)
            ->where('course_id', $courseId)
            ->where('lesson_id', $lessonId)
            ->update([
                'position_seconds' => $position,
                'watched_seconds' => DB::raw(
                    'CASE WHEN watched_seconds > '.$position.' THEN watched_seconds ELSE '.$position.' END',
                ),
                'last_activity_at' => $now,
            ]);
    }
}
