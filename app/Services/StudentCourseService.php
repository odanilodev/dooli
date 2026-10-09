<?php

namespace App\Services;

use App\Enums\ActionCode;
use App\Enums\GeneralStatus;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\UnauthorizedCourseAccessException;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Team;
use App\Models\User;
use App\Repositories\ActionRepository;
use App\Repositories\TimelineRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class StudentCourseService
{
    public function __construct(
        private readonly DatalogService $audit,
        private readonly TimelineRepository $timeline,
        private readonly ActionRepository $actions,
        private readonly ScheduleService $schedules,
    ) {}

    public function myCourses(User $user, int $perPage = 20, ?int $teamId = null): LengthAwarePaginator
    {
        return $this->enrolledCoursesQuery($user, $teamId)->paginate($perPage);
    }

    /**
     * Every active course the user is enrolled in, ordered for display.
     *
     * @return Collection<int, Course>
     */
    public function enrolledCourses(User $user, ?int $teamId = null): Collection
    {
        return $this->enrolledCoursesQuery($user, $teamId)->get();
    }

    /**
     * Active courses the user is enrolled in across all their teams, with the
     * matching active teams eager-loaded. Built for the grouped home so the
     * courses (and their team memberships) are resolved in a single query.
     *
     * @return Collection<int, Course>
     */
    public function enrolledCoursesForTeams(User $user): Collection
    {
        $teamConstraint = function ($query) use ($user) {
            $query->where('teams.status', GeneralStatus::Active->value)
                ->where('team_courses.status', GeneralStatus::Active->value)
                ->whereHas('users', fn ($u) => $this->activeEnrollmentConstraint($u, $user->id));
        };

        return Course::query()
            ->where('status', GeneralStatus::Active->value)
            ->whereHas('teams', $teamConstraint)
            ->with(['teams' => $teamConstraint])
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Active teams the user is enrolled in, ordered for display.
     *
     * @return Collection<int, Team>
     */
    public function myTeams(User $user): Collection
    {
        return Team::query()
            ->where('status', GeneralStatus::Active->value)
            ->whereHas('users', fn ($q) => $this->activeEnrollmentConstraint($q, $user->id))
            ->withCount('courses')
            ->orderBy('sort_order')
            ->get();
    }

    private function enrolledCoursesQuery(User $user, ?int $teamId = null): Builder
    {
        return Course::query()
            ->where('status', GeneralStatus::Active->value)
            ->whereHas('teams', function ($q) use ($user, $teamId) {
                $q->where('teams.status', GeneralStatus::Active->value)
                    ->whereHas('users', fn ($u) => $this->activeEnrollmentConstraint($u, $user->id))
                    ->where('team_courses.status', GeneralStatus::Active->value)
                    ->when($teamId, fn ($t) => $t->where('teams.id', $teamId));
            })
            ->orderBy('sort_order');
    }

    /**
     * @return Collection<int, Team>
     */
    public function accessibleTeams(User $user, int $courseId): Collection
    {
        return Team::query()
            ->where('status', GeneralStatus::Active->value)
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
                $q->where('teams.status', GeneralStatus::Active->value)
                    ->whereHas('users', fn ($u) => $this->activeEnrollmentConstraint($u, $user->id))
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
            ->whereHas('module.courses', fn ($q) => $q->where('courses.id', $courseId))
            ->exists();

        if (! $belongsToCourse) {
            throw new BusinessRuleException('Aula não pertence a este curso.');
        }

        return $team;
    }

    /**
     * A lesson can only be opened/completed when it is already released by the
     * team schedule and its prerequisite (if any) was completed.
     */
    public function assertLessonUnlocked(User $user, Lesson $lesson, int $courseId, int $teamId): void
    {
        $this->assertLessonScheduled($lesson, $courseId, $teamId);

        $requiredLessonId = $lesson->required_lesson_id;

        if (! $requiredLessonId) {
            return;
        }

        $completedAction = $this->actions->findByCode(ActionCode::Completed->value);

        if (! $completedAction) {
            return;
        }

        $completed = $this->timeline->existsFor(
            $user->id,
            (int) $requiredLessonId,
            $courseId,
            $teamId,
            $completedAction->id,
        );

        if (! $completed) {
            $requiredName = Lesson::query()->whereKey($requiredLessonId)->value('name') ?? 'aula anterior';

            throw new BusinessRuleException("Conclua a aula \"{$requiredName}\" para liberar esta aula.");
        }
    }

    /**
     * Asserts the lesson is released for the team (course, module and lesson
     * schedules combined — the most restrictive date wins).
     */
    private function assertLessonScheduled(Lesson $lesson, int $courseId, int $teamId): void
    {
        $availableAt = $this->schedules->effectiveForLesson($teamId, $courseId, $lesson->module_id, $lesson->id);

        $this->schedules->assertLessonAvailable($availableAt);
    }

    public function courseContent(User $user, int $courseId, ?int $teamId = null): Course
    {
        $team = $this->assertAccess($user, $courseId, $teamId);

        $course = Course::query()
            ->with([
                'modules' => fn ($q) => $q->orderBy('course_modules.sort_order'),
                'modules.topics' => fn ($q) => $q->where('status', GeneralStatus::Active->value)->orderBy('sort_order'),
                'modules.topics.lessons' => fn ($q) => $q->where('lessons.status', GeneralStatus::Active->value)
                    ->orderBy('sort_order')
                    ->with(['video', 'document', 'attachments']),
                'modules.looseLessons' => fn ($q) => $q->where('lessons.status', GeneralStatus::Active->value)
                    ->orderBy('sort_order')
                    ->with(['video', 'document', 'attachments']),
            ])
            ->findOrFail($courseId);

        $this->schedules->applyToCourseContent($course, $team);

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
