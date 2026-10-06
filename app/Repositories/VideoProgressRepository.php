<?php

namespace App\Repositories;

use App\Enums\GeneralStatus;
use App\Models\VideoProgress;

class VideoProgressRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(new VideoProgress);
    }

    public function forLesson(int $userId, int $lessonId, int $courseId, int $teamId): ?VideoProgress
    {
        return $this->query()
            ->where('user_id', $userId)
            ->where('lesson_id', $lessonId)
            ->where('course_id', $courseId)
            ->where('team_id', $teamId)
            ->first();
    }

    /**
     * Most recent in-progress video of the user in a course/team, with the
     * lesson (and its video) loaded for the "continue watching" shelf.
     */
    public function latestForCourse(int $userId, int $courseId, int $teamId): ?VideoProgress
    {
        return $this->query()
            ->with(['lesson.module', 'lesson.video'])
            ->where('user_id', $userId)
            ->where('course_id', $courseId)
            ->where('team_id', $teamId)
            ->where('position_seconds', '>', 0)
            ->whereHas('lesson', fn ($q) => $q->where('lessons.status', GeneralStatus::Active->value))
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();
    }

    public function savePosition(
        int $userId,
        int $lessonId,
        int $courseId,
        int $teamId,
        int $position,
        ?int $duration,
    ): VideoProgress {
        return $this->query()->updateOrCreate(
            [
                'user_id' => $userId,
                'lesson_id' => $lessonId,
                'course_id' => $courseId,
                'team_id' => $teamId,
            ],
            [
                'position_seconds' => $position,
                'duration' => $duration,
            ],
        );
    }
}
