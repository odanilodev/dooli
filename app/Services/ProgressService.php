<?php

namespace App\Services;

use App\DTO\CompleteLessonDTO;
use App\DTO\StartLessonDTO;
use App\Enums\ActionCode;
use App\Exceptions\BusinessRuleException;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Team;
use App\Models\Timeline;
use App\Models\User;
use App\Repositories\ActionRepository;
use App\Repositories\CourseCompletionRepository;
use App\Repositories\LessonRepository;
use App\Repositories\StudentLessonProgressRepository;
use App\Repositories\TimelineRepository;
use App\Repositories\VideoProgressRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ProgressService
{
    /**
     * Seconds of tolerance so a video that ends a moment before its declared
     * duration still counts as fully watched.
     */
    private const WATCH_TOLERANCE_SECONDS = 2;

    public function __construct(
        private readonly TimelineRepository $timeline,
        private readonly LessonRepository $lessons,
        private readonly ActionRepository $actions,
        private readonly StudentCourseService $studentCourses,
        private readonly CertificateService $certificates,
        private readonly DatalogService $audit,
        private readonly VideoProgressRepository $videoProgress,
        private readonly StudentLessonProgressRepository $snapshot,
        private readonly CourseCompletionRepository $completions,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function completeLesson(User $user, CompleteLessonDTO $dto): array
    {
        $team = $this->studentCourses->assertAccess($user, $dto->courseId, $dto->teamId);

        $lesson = Lesson::query()->with('module.courses')->findOrFail($dto->lessonId);

        $module = $lesson->module;

        $belongsToCourse = $module !== null
            && $module->courses->contains(fn (Course $course) => $course->id === $dto->courseId);

        if (! $belongsToCourse) {
            throw new BusinessRuleException('Aula não pertence a este curso.');
        }

        $this->studentCourses->assertLessonUnlocked($user, $lesson, $dto->courseId, $team->id);

        $action = $this->actions->findByCode(ActionCode::Completed->value);

        if (! $action) {
            throw new BusinessRuleException('Ação de conclusão não configurada para esta empresa.');
        }

        $alreadyCompleted = $this->timeline->existsFor(
            $user->id,
            $lesson->id,
            $dto->courseId,
            $team->id,
            $action->id,
        );

        if (! $alreadyCompleted) {
            $this->assertFullWatchSatisfied($user, $lesson, $dto, $team->id);

            DB::transaction(function () use ($user, $lesson, $dto, $team, $action, $module) {
                Timeline::query()->create([
                    'user_id' => $user->id,
                    'company_id' => $user->company_id,
                    'module_id' => $module->id,
                    'lesson_id' => $lesson->id,
                    'course_id' => $dto->courseId,
                    'action_id' => $action->id,
                    'team_id' => $team->id,
                ]);

                $this->snapshot->markCompleted(
                    $user->id,
                    $lesson->id,
                    $dto->courseId,
                    $team->id,
                    $module->id,
                );

                $this->recordCompletionIfFinished($user, $dto->courseId, $team->id, $action->id);
            });

            $this->audit->log('progress', 'complete_lesson', 'lesson', "Aula concluída: {$lesson->name}", $user->id);
        }

        // Issuance runs outside the transaction (and even for already completed
        // lessons) so a failure here never loses the completion and is retried
        // on the next interaction.
        $this->certificates->issueIfEligible($user, $dto->courseId, $team->id);

        return [
            'already_completed' => $alreadyCompleted,
            'progress' => $this->progressFor($user, $dto->courseId, $team->id),
        ];
    }

    /**
     * Persists the historical course completion the first time the student
     * reaches the total number of required lessons.
     */
    private function recordCompletionIfFinished(User $user, int $courseId, int $teamId, int $actionId): void
    {
        $total = $this->lessons->countPublishedByCourse($courseId);

        if ($total === 0) {
            return;
        }

        $completedIds = $this->timeline->completedLessonIds($user->id, $courseId, $teamId, $actionId);

        if (count($completedIds) < $total) {
            return;
        }

        $this->completions->markCompleted(
            (int) $user->id,
            $teamId,
            $courseId,
            (int) $user->company_id,
            $total,
            array_map('intval', $completedIds),
        );
    }

    /**
     * A lesson flagged with require_full_watch can only be completed after the
     * student watched the video up to its end (within a small tolerance).
     */
    private function assertFullWatchSatisfied(User $user, Lesson $lesson, CompleteLessonDTO $dto, int $teamId): void
    {
        if (! $lesson->require_full_watch) {
            return;
        }

        $lesson->loadMissing('video');

        if ($lesson->video_id === null) {
            return;
        }

        $progress = $this->videoProgress->forLesson($user->id, $lesson->id, $dto->courseId, $teamId);

        $position = (int) ($progress?->position_seconds ?? 0);
        $duration = (int) ($progress?->duration ?: $lesson->video?->time ?? 0);

        $watchedToEnd = $duration > 0
            ? $position >= $duration - self::WATCH_TOLERANCE_SECONDS
            : $position > 0;

        if (! $watchedToEnd) {
            throw new BusinessRuleException('Assista a aula até o final para concluí-la.');
        }
    }

    /**
     * Records that the student opened a lesson. Two timeline actions are kept:
     * "started" is written once per course/team (the first time the lesson is
     * opened in that enrollment), while "viewed" is upserted — the same row is
     * updated (timestamp) on every revisit, so it always reflects the last time
     * the screen was seen. The materialized snapshot is written in the same
     * transaction so it can never drift from the timeline.
     *
     * @return array{already_started: bool}
     */
    public function startLesson(User $user, StartLessonDTO $dto): array
    {
        $team = $this->studentCourses->assertLessonInCourse($user, $dto->courseId, $dto->lessonId, $dto->teamId);

        $lesson = Lesson::query()->with('module')->findOrFail($dto->lessonId);

        $this->studentCourses->assertLessonUnlocked($user, $lesson, $dto->courseId, $team->id);

        $module = $lesson->module;

        $attributes = [
            'company_id' => $user->company_id,
            'module_id' => $module?->id,
            'lesson_id' => $lesson->id,
            'course_id' => $dto->courseId,
            'team_id' => $team->id,
        ];

        $startedAction = $this->actions->findByCode(ActionCode::Started->value);
        $alreadyStarted = $startedAction === null
            || $this->timeline->existsFor($user->id, $lesson->id, $dto->courseId, $team->id, $startedAction->id);

        DB::transaction(function () use ($user, $lesson, $dto, $team, $module, $attributes, $startedAction, $alreadyStarted) {
            if ($startedAction !== null && ! $alreadyStarted) {
                Timeline::query()->create([
                    'user_id' => $user->id,
                    'action_id' => $startedAction->id,
                    ...$attributes,
                ]);
            }

            if ($module) {
                $this->snapshot->markStarted($user->id, $lesson->id, $dto->courseId, $team->id, $module->id);
            }
        });

        if ($startedAction !== null && ! $alreadyStarted) {
            $this->audit->log('progress', 'start_lesson', 'lesson', "Aula iniciada: {$lesson->name}", $user->id);
        }

        $this->recordViewed($user, $attributes);

        return ['already_started' => $alreadyStarted];
    }

    /**
     * Upserts the "viewed" action for a lesson: creates it on the first view and
     * only touches the existing row on subsequent views.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function recordViewed(User $user, array $attributes): void
    {
        $viewedAction = $this->actions->findByCode(ActionCode::Viewed->value);

        if (! $viewedAction) {
            return;
        }

        $existing = $this->timeline->findForLesson($user->id, $attributes['lesson_id'], $viewedAction->id);

        if ($existing) {
            $existing->touch();

            return;
        }

        Timeline::query()->create([
            'user_id' => $user->id,
            'action_id' => $viewedAction->id,
            ...$attributes,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function progressFor(User $user, int $courseId, int $teamId): array
    {
        $completedAction = $this->actions->findByCode(ActionCode::Completed->value);
        $startedAction = $this->actions->findByCode(ActionCode::Started->value);

        $totalLessons = $this->lessons->countPublishedByCourse($courseId);

        $completedIds = $completedAction
            ? $this->timeline->completedLessonIds($user->id, $courseId, $teamId, $completedAction->id)
            : [];

        $startedIds = $startedAction
            ? $this->timeline->lessonIdsForAction($user->id, $courseId, $teamId, $startedAction->id)
            : [];

        $completedCount = count($completedIds);

        $completion = $this->completions->findForUserCourseTeam($user->id, $courseId, $teamId);
        $requiredLessons = (int) ($completion?->required_lessons ?? 0);

        return [
            'course_id' => $courseId,
            'team_id' => $teamId,
            'total_lessons' => $totalLessons,
            'completed_lessons' => $completedCount,
            'percentage' => $totalLessons > 0 ? round(($completedCount / $totalLessons) * 100, 2) : 0.0,
            'completed' => $completion !== null,
            'completed_at' => $completion?->completed_at?->toIso8601String(),
            'required_lessons' => $requiredLessons,
            'new_lessons_since_completion' => $completion ? max(0, $totalLessons - $requiredLessons) : 0,
            'completed_lesson_ids' => $completedIds,
            'started_lesson_ids' => $startedIds,
        ];
    }

    /**
     * The team to read progress from: the selected one when given, otherwise
     * the first team the user can access for the course.
     */
    private function resolveTeam(User $user, int $courseId, ?int $teamId): ?Team
    {
        $teams = $this->studentCourses->accessibleTeams($user, $courseId);

        return $teamId ? $teams->firstWhere('id', $teamId) : $teams->first();
    }

    /**
     * @return array<int, array{total_lessons: int, completed_lessons: int, percentage: float, completed: bool, completed_at: ?string, required_lessons: int, new_lessons_since_completion: int}>
     */
    public function progressSummaryFor(User $user, Collection $courses, ?int $teamId = null): array
    {
        $completedAction = $this->actions->findByCode(ActionCode::Completed->value);

        $completionInfo = $this->completions->completionInfoForUser($user->id);

        $summary = [];

        foreach ($courses as $course) {
            $team = $this->resolveTeam($user, $course->id, $teamId);

            if (! $team) {
                $summary[$course->id] = [
                    'total_lessons' => 0,
                    'completed_lessons' => 0,
                    'percentage' => 0.0,
                    'completed' => false,
                    'completed_at' => null,
                    'required_lessons' => 0,
                    'new_lessons_since_completion' => 0,
                ];

                continue;
            }

            $totalLessons = $this->lessons->countPublishedByCourse($course->id);

            $completedCount = $completedAction
                ? count($this->timeline->completedLessonIds($user->id, $course->id, $team->id, $completedAction->id))
                : 0;

            $info = $completionInfo[$course->id.':'.$team->id] ?? null;
            $requiredLessons = (int) ($info['required_lessons'] ?? 0);

            $summary[$course->id] = [
                'total_lessons' => $totalLessons,
                'completed_lessons' => $completedCount,
                'percentage' => $totalLessons > 0 ? round(($completedCount / $totalLessons) * 100, 2) : 0.0,
                'completed' => $info !== null,
                'completed_at' => $info['completed_at'] ?? null,
                'required_lessons' => $requiredLessons,
                'new_lessons_since_completion' => $info !== null ? max(0, $totalLessons - $requiredLessons) : 0,
            ];
        }

        return $summary;
    }

    /**
     * Courses the student started but has not finished yet, paired with the
     * lesson to continue (the last one with a saved video position, or the
     * next published lesson). Drives the "continue watching" shelf.
     *
     * @return array<int, array<string, mixed>>
     */
    public function continueWatching(User $user, ?int $teamId = null): array
    {
        $completedAction = $this->actions->findByCode(ActionCode::Completed->value);

        if (! $completedAction) {
            return [];
        }

        $shelf = [];

        $completionInfo = $this->completions->completionInfoForUser($user->id);

        foreach ($this->studentCourses->enrolledCourses($user, $teamId) as $course) {
            $team = $this->resolveTeam($user, $course->id, $teamId);

            if (! $team) {
                continue;
            }

            if (isset($completionInfo[$course->id.':'.$team->id])) {
                continue;
            }

            $totalLessons = $this->lessons->countPublishedByCourse($course->id);

            if ($totalLessons === 0) {
                continue;
            }

            $completedIds = $this->timeline->completedLessonIds($user->id, $course->id, $team->id, $completedAction->id);
            $completedCount = count($completedIds);

            if ($completedCount >= $totalLessons) {
                continue;
            }

            $target = $this->continueTarget($user, $course->id, $team->id, $completedIds);

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
                'percentage' => round(($completedCount / $totalLessons) * 100, 2),
            ];
        }

        return $shelf;
    }

    /**
     * Lesson the student should continue with: the one with the most recent
     * saved video position (to resume), or the next incomplete lesson.
     *
     * @param  array<int, int>  $completedIds
     * @return array{lesson: Lesson, duration: int|null, resume_from: int}|null
     */
    private function continueTarget(User $user, int $courseId, int $teamId, array $completedIds): ?array
    {
        $resume = $this->videoProgress->latestForCourse($user->id, $courseId, $teamId);

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

        $nextLesson = $this->lessons->nextIncompleteForCourse($courseId, $completedIds);

        if (! $nextLesson) {
            return null;
        }

        return [
            'lesson' => $nextLesson,
            'duration' => $nextLesson->video?->time,
            'resume_from' => 0,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function history(User $user, int $courseId, int $teamId): array
    {
        return Timeline::query()
            ->with(['lesson', 'action', 'module'])
            ->where('user_id', $user->id)
            ->where('course_id', $courseId)
            ->where('team_id', $teamId)
            ->orderByDesc('id')
            ->get()
            ->map(fn (Timeline $item) => [
                'id' => $item->id,
                'action' => $item->action?->code,
                'lesson_id' => $item->lesson_id,
                'lesson_name' => $item->lesson?->name,
                'module_id' => $item->module_id,
                'created_at' => $item->created_at?->toIso8601String(),
            ])
            ->all();
    }
}
