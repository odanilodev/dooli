<?php

namespace App\Repositories;

use App\Models\CourseCompletion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;

class CourseCompletionRepository extends BaseRepository
{
    protected array $filterable = ['company_id', 'user_id', 'team_id', 'course_id'];

    public function __construct()
    {
        parent::__construct(new CourseCompletion);
    }

    /**
     * Records the historical completion of a course for a user+team. Idempotent:
     * the first completion wins and is never overwritten, even under concurrency
     * (the unique key on user+team+course guarantees a single row).
     *
     * @param  array<int, int>  $lessonIds
     */
    public function markCompleted(int $userId, int $teamId, int $courseId, int $companyId, int $requiredLessons, array $lessonIds): CourseCompletion
    {
        $completion = $this->findForUserCourseTeam($userId, $courseId, $teamId);

        if ($completion) {
            return $completion;
        }

        try {
            return $this->create([
                'company_id' => $companyId,
                'user_id' => $userId,
                'team_id' => $teamId,
                'course_id' => $courseId,
                'required_lessons' => $requiredLessons,
                'lesson_ids' => $lessonIds,
                'completed_at' => now(),
            ]);
        } catch (QueryException $e) {
            return $this->query()
                ->where('user_id', $userId)
                ->where('course_id', $courseId)
                ->where('team_id', $teamId)
                ->firstOrFail();
        }
    }

    public function findForUserCourseTeam(int $userId, int $courseId, int $teamId): ?CourseCompletion
    {
        return $this->query()
            ->where('user_id', $userId)
            ->where('course_id', $courseId)
            ->where('team_id', $teamId)
            ->first();
    }

    /**
     * Completion info keyed as "courseId:teamId", so callers can resolve the
     * historical completion and the requirement snapshot without extra queries.
     *
     * @param  array<int, int>  $teamIds
     * @return array<string, array{required_lessons: int, completed_at: ?string}>
     */
    public function completionInfoForUser(int $userId, array $teamIds = []): array
    {
        $query = $this->query()->where('user_id', $userId);

        if ($teamIds !== []) {
            $query->whereIn('team_id', $teamIds);
        }

        return $query->get(['course_id', 'team_id', 'required_lessons', 'completed_at'])
            ->mapWithKeys(fn (CourseCompletion $row) => [
                $row->course_id.':'.$row->team_id => [
                    'required_lessons' => (int) $row->required_lessons,
                    'completed_at' => $row->completed_at?->toIso8601String(),
                ],
            ])
            ->all();
    }

    /**
     * All completions of a course for a company, ignoring the request tenant
     * scope (used by backfill/retroactive issuance commands and services).
     *
     * @return Collection<int, CourseCompletion>
     */
    public function forCourse(int $courseId, int $companyId): Collection
    {
        return $this->query()
            ->withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->where('course_id', $courseId)
            ->get();
    }
}
