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
     * The timeline row for a user/lesson/action, regardless of course/team.
     */
    public function findForLesson(int $userId, int $lessonId, int $actionId): ?Timeline
    {
        return $this->query()
            ->where('user_id', $userId)
            ->where('lesson_id', $lessonId)
            ->where('action_id', $actionId)
            ->first();
    }

    /**
     * Whether the user already has this action recorded for the lesson,
     * regardless of course/team (one "started" row per lesson).
     */
    public function existsForLesson(int $userId, int $lessonId, int $actionId): bool
    {
        return $this->query()
            ->where('user_id', $userId)
            ->where('lesson_id', $lessonId)
            ->where('action_id', $actionId)
            ->exists();
    }

    /**
     * @return array<int, int> Lesson ids with the given action for a user in a course/team.
     */
    public function lessonIdsForAction(int $userId, int $courseId, int $teamId, int $actionId): array
    {
        return $this->query()
            ->where('user_id', $userId)
            ->where('course_id', $courseId)
            ->where('team_id', $teamId)
            ->where('action_id', $actionId)
            ->pluck('lesson_id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, int> Completed lesson ids for a user in a course/team.
     */
    public function completedLessonIds(int $userId, int $courseId, int $teamId, int $completedActionId): array
    {
        return $this->lessonIdsForAction($userId, $courseId, $teamId, $completedActionId);
    }
}
