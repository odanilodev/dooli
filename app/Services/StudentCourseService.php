<?php

namespace App\Services;

use App\Enums\GeneralStatus;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\UnauthorizedCourseAccessException;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class StudentCourseService
{
    public function __construct(private readonly DatalogService $audit) {}

    public function myCourses(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return $this->enrolledCoursesQuery($user)->paginate($perPage);
    }

    /**
     * Every active course the user is enrolled in, ordered for display.
     *
     * @return Collection<int, Course>
     */
    public function enrolledCourses(User $user): Collection
    {
        return $this->enrolledCoursesQuery($user)->get();
    }

    private function enrolledCoursesQuery(User $user): Builder
    {
        return Course::query()
            ->where('status', GeneralStatus::Active->value)
            ->whereHas('teams', function ($q) use ($user) {
                $q->whereHas('users', fn ($u) => $this->activeEnrollmentConstraint($u, $user->id))
                    ->where('team_courses.status', GeneralStatus::Active->value);
            })
            ->orderBy('sort_order');
    }

    /**
     * @return Collection<int, Team>
     */
    public function accessibleTeams(User $user, int $courseId): Collection
    {
        return Team::query()
            ->whereHas('users', fn ($q) => $this->activeEnrollmentConstraint($q, $user->id))
            ->whereHas('courses', fn ($q) => $q->where('courses.id', $courseId)
                ->where('team_courses.status', GeneralStatus::Active->value))
            ->get();
    }

    public function assertAccess(User $user, int $courseId, ?int $teamId = null): Team
    {
        $teams = $this->accessibleTeams($user, $courseId);

        $team = $teamId
            ? $teams->firstWhere('id', $teamId)
            : $teams->first();

        if (! $team) {
            throw new UnauthorizedCourseAccessException;
        }

        return $team;
    }

    /**
     * Ids of the courses the user is actively enrolled in.
     *
     * @return array<int, int>
     */
    public function enrolledCourseIds(User $user): array
    {
        return Course::query()
            ->whereHas('teams', function ($q) use ($user) {
                $q->whereHas('users', fn ($u) => $this->activeEnrollmentConstraint($u, $user->id))
                    ->where('team_courses.status', GeneralStatus::Active->value);
            })
            ->pluck('id')
            ->all();
    }

    /**
     * Asserts the user is enrolled in the course and that the lesson really
     * belongs to it (prevents cross-course/cross-tenant references).
     */
    public function assertLessonInCourse(User $user, int $courseId, int $lessonId, ?int $teamId = null): Team
    {
        $team = $this->assertAccess($user, $courseId, $teamId);

        $belongsToCourse = Lesson::query()
            ->whereKey($lessonId)
            ->whereHas('topic.module.courses', fn ($q) => $q->where('courses.id', $courseId))
            ->exists();

        if (! $belongsToCourse) {
            throw new BusinessRuleException('Aula não pertence a este curso.');
        }

        return $team;
    }

    public function courseContent(User $user, int $courseId): Course
    {
        $this->assertAccess($user, $courseId);

        $course = Course::query()
            ->with([
                'modules' => fn ($q) => $q->orderBy('course_modules.sort_order'),
                'modules.topics' => fn ($q) => $q->where('status', GeneralStatus::Active->value)->orderBy('sort_order'),
                'modules.topics.lessons' => fn ($q) => $q->where('lessons.status', GeneralStatus::Active->value)
                    ->orderBy('sort_order')
                    ->with(['video', 'document', 'attachments']),
            ])
            ->findOrFail($courseId);

        $this->audit->log('student', 'view_course', 'course', "Aluno acessou o curso {$course->name}", $user->id);

        return $course;
    }

    private function activeEnrollmentConstraint($query, int $userId): void
    {
        $query->where('users.id', $userId)
            ->where('team_users.status', GeneralStatus::Active->value)
            ->where(function ($q) {
                $q->whereNull('team_users.expired_at')
                    ->orWhere('team_users.expired_at', '>', now());
            });
    }
}
