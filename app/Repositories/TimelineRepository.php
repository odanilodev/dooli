<?php

namespace App\Repositories;

use App\Models\Timeline;

class TimelineRepository extends BaseRepository
{
    protected array $filterable = ['user_id', 'course_id', 'lesson_id', 'action_id', 'team_id', 'company_id'];

    protected string $defaultSort = 'id';

    public function __construct()
    {
        parent::__construct(new Timeline);
    }

    public function existsFor(int $userId, int $lessonId, int $courseId, int $teamId, int $actionId): bool
    {
        return $this->query()
            ->where('user_id', $userId)
            ->where('lesson_id', $lessonId)
            ->where('course_id', $courseId)
            ->where('team_id', $teamId)
            ->where('action_id', $actionId)
            ->exists();
    }

    /**
     * @return array<int, int> Completed lesson ids for a user in a course/team.
     */
    public function completedLessonIds(int $userId, int $courseId, int $teamId, int $completedActionId): array
    {
        return $this->query()
            ->where('user_id', $userId)
            ->where('course_id', $courseId)
            ->where('team_id', $teamId)
            ->where('action_id', $completedActionId)
            ->pluck('lesson_id')
            ->unique()
            ->values()
            ->all();
    }
}
