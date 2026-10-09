<?php

namespace App\Repositories;

use App\Models\Timeline;
use Illuminate\Support\Collection;

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

    /**
     * Completed lesson ids for a user across many courses/teams, grouped by
     * "course_id:team_id" in a single query.
     *
     * @param  array<int, int>  $courseIds
     * @param  array<int, int>  $teamIds
     * @return array<string, array<int, int>>
     */
    public function completedLessonIdsGrouped(int $userId, array $courseIds, array $teamIds, int $actionId): array
    {
        if ($courseIds === [] || $teamIds === []) {
            return [];
        }

        return $this->query()
            ->where('user_id', $userId)
            ->where('action_id', $actionId)
            ->whereIn('course_id', $courseIds)
            ->whereIn('team_id', $teamIds)
            ->get(['course_id', 'team_id', 'lesson_id'])
            ->groupBy(fn (Timeline $row) => $row->course_id.':'.$row->team_id)
            ->map(fn (Collection $rows) => $rows->pluck('lesson_id')->unique()->values()->all())
            ->all();
    }
}
