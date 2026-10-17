<?php

namespace App\Services;

use App\Enums\ActionCode;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Team;
use App\Models\User;
use App\Models\VideoProgress;
use App\Repositories\ActionRepository;
use App\Repositories\CourseCompletionRepository;
use App\Repositories\LessonRepository;
use App\Repositories\TimelineRepository;
use App\Repositories\VideoProgressRepository;
use Illuminate\Database\Eloquent\Collection;

class StudentHomeService
{
    public function __construct(
        private readonly StudentCourseService $studentCourses,
        private readonly LessonRepository $lessons,
        private readonly TimelineRepository $timeline,
        private readonly VideoProgressRepository $videoProgress,
        private readonly ActionRepository $actions,
        private readonly CourseCompletionRepository $completions,
    ) {}

    /**
     * Active teams of the user, each with its courses (progress included) and
     * the "continue watching" shelf scoped to that team.
     *
     * The data is resolved with a constant number of bulk queries, independent
     * of how many teams or courses the user is enrolled in.
     *
     * @return Collection<int, Team>
     */
    public function forUser(User $user): Collection
    {
        $teams = $this->studentCourses->myTeams($user);
        $courses = $this->studentCourses->enrolledCoursesForTeams($user);

        $courseIds = $courses->pluck('id')->unique()->values()->all();
        $teamIds = $teams->pluck('id')->all();

        $totals = $this->lessons->publishedCountsForCourses($courseIds);

        $completedAction = $this->actions->findByCode(ActionCode::Completed->value);

        $completed = $completedAction
            ? $this->timeline->completedLessonIdsGrouped($user->id, $courseIds, $teamIds, $completedAction->id)
            : [];

        $completedKeys = $this->completions->completionInfoForUser($user->id);

        $resumes = $this->videoProgress->latestForCourses($user->id, $courseIds, $teamIds);
        $lessonsByCourse = $this->lessons->publishedLessonsByCourse($courseIds);

        $coursesByTeam = [];

        foreach ($courses as $course) {
            foreach ($course->teams as $team) {
                $coursesByTeam[$team->id][] = $course;
            }
        }

        return $teams->map(function (Team $team) use ($coursesByTeam, $totals, $completed, $completedKeys, $resumes, $lessonsByCourse, $completedAction) {
            $teamCourses = collect($coursesByTeam[$team->id] ?? []);

            $homeCourses = $teamCourses->map(function (Course $course) use ($team, $totals, $completed, $completedKeys) {
                $total = (int) ($totals[$course->id] ?? 0);
                $completedIds = $completed[$course->id.':'.$team->id] ?? [];
                $done = count($completedIds);

                $info = $completedKeys[$course->id.':'.$team->id] ?? null;
                $requiredLessons = (int) ($info['required_lessons'] ?? 0);

                $clone = clone $course;
                $clone->setAttribute('progress', [
                    'total_lessons' => $total,
                    'completed_lessons' => $done,
                    'percentage' => $total > 0 ? round(($done / $total) * 100, 2) : 0.0,
                    'completed' => $info !== null,
                    'completed_at' => $info['completed_at'] ?? null,
                    'required_lessons' => $requiredLessons,
                    'new_lessons_since_completion' => $info !== null ? max(0, $total - $requiredLessons) : 0,
                ]);

                return $clone;
            });

            $team->setRelation('homeCourses', $homeCourses);
            $team->setAttribute(
                'continue_watching',
                $completedAction
                    ? $this->continueWatchingForTeam($team, $teamCourses, $totals, $completed, $completedKeys, $resumes, $lessonsByCourse)
                    : [],
            );

            return $team;
        })->values();
    }

    /**
     * @param  iterable<int, Course>  $teamCourses
     * @param  array<int, int>  $totals
     * @param  array<string, array<int, int>>  $completed
     * @param  array<string, array{required_lessons: int, completed_at: ?string}>  $completedKeys
     * @return array<int, array<string, mixed>>
     */
    private function continueWatchingForTeam(
        Team $team,
        iterable $teamCourses,
        array $totals,
        array $completed,
        array $completedKeys,
        Collection $resumes,
        Collection $lessonsByCourse,
    ): array {
        $shelf = [];

        foreach ($teamCourses as $course) {
            $total = (int) ($totals[$course->id] ?? 0);

            if ($total === 0) {
                continue;
            }

            if (isset($completedKeys[$course->id.':'.$team->id])) {
                continue;
            }

            $completedIds = $completed[$course->id.':'.$team->id] ?? [];
            $done = count($completedIds);

            if ($done >= $total) {
                continue;
            }

            $target = $this->continueTarget(
                $resumes->get($course->id.':'.$team->id),
                $completedIds,
                $lessonsByCourse->get($course->id) ?? new Collection,
            );

            if (! $target) {
                continue;
            }

            $shelf[] = [
                'course_id' => $course->id,
                'course_name' => $course->name,
                'lesson_id' => $target['lesson']->id,
                'lesson_name' => $target['lesson']->name,
                'module_name' => $target['lesson']->module?->name,
                'image' => $target['lesson']->video?->imageUrl() ?? $course->imageUrl(),
                'duration' => $target['duration'],
                'resume_from' => $target['resume_from'],
                'percentage' => round(($done / $total) * 100, 2),
            ];
        }

        return $shelf;
    }

    /**
     * Lesson the student should continue with: the one with the most recent
     * saved video position (to resume), or the next incomplete lesson. Mirrors
     * ProgressService::continueTarget.
     *
     * @param  array<int, int>  $completedIds
     * @param  iterable<int, Lesson>  $orderedLessons
     * @return array{lesson: Lesson, duration: int|null, resume_from: int}|null
     */
    private function continueTarget(?VideoProgress $resume, array $completedIds, iterable $orderedLessons): ?array
    {
        if ($resume?->lesson) {
            return [
                'lesson' => $resume->lesson,
                'duration' => $resume->duration ?? $resume->lesson->video?->time,
                'resume_from' => $resume->position_seconds,
            ];
        }

        if ($completedIds === []) {
            return null;
        }

        $next = collect($orderedLessons)->first(
            fn (Lesson $lesson) => ! in_array($lesson->id, $completedIds, true),
        );

        if (! $next) {
            return null;
        }

        return [
            'lesson' => $next,
            'duration' => $next->video?->time,
            'resume_from' => 0,
        ];
    }
}
