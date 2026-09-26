<?php

namespace App\Services;

use App\DTO\CompleteLessonDTO;
use App\Enums\ActionCode;
use App\Exceptions\BusinessRuleException;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Timeline;
use App\Models\User;
use App\Repositories\ActionRepository;
use App\Repositories\LessonRepository;
use App\Repositories\TimelineRepository;
use App\Repositories\VideoProgressRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ProgressService
{
    public function __construct(
        private readonly TimelineRepository $timeline,
        private readonly LessonRepository $lessons,
        private readonly ActionRepository $actions,
        private readonly StudentCourseService $studentCourses,
        private readonly CertificateService $certificates,
        private readonly DatalogService $audit,
        private readonly VideoProgressRepository $videoProgress,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function completeLesson(User $user, CompleteLessonDTO $dto): array
    {
        $team = $this->studentCourses->assertAccess($user, $dto->courseId, $dto->teamId);

        $lesson = Lesson::query()->with('topic.module.courses')->findOrFail($dto->lessonId);

        $module = $lesson->topic?->module;

        $belongsToCourse = $module !== null
            && $module->courses->contains(fn (Course $course) => $course->id === $dto->courseId);

        if (! $belongsToCourse) {
            throw new BusinessRuleException('Aula não pertence a este curso.');
        }

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
            });

            $this->audit->log('progress', 'complete_lesson', 'lesson', "Aula concluída: {$lesson->name}", $user->id);

            $this->certificates->issueIfEligible($user, $dto->courseId, $team->id);
        }

        return [
            'already_completed' => $alreadyCompleted,
            'progress' => $this->progressFor($user, $dto->courseId, $team->id),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function progressFor(User $user, int $courseId, int $teamId): array
    {
        $completedAction = $this->actions->findByCode(ActionCode::Completed->value);

        $totalLessons = $this->lessons->countPublishedByCourse($courseId);

        $completedIds = $completedAction
            ? $this->timeline->completedLessonIds($user->id, $courseId, $teamId, $completedAction->id)
            : [];

        $completedCount = count($completedIds);

        return [
            'course_id' => $courseId,
            'team_id' => $teamId,
            'total_lessons' => $totalLessons,
            'completed_lessons' => $completedCount,
            'percentage' => $totalLessons > 0 ? round(($completedCount / $totalLessons) * 100, 2) : 0.0,
            'completed_lesson_ids' => $completedIds,
        ];
    }

    /**
     * @return array<int, array{total_lessons: int, completed_lessons: int, percentage: float}>
     */
    public function progressSummaryFor(User $user, Collection $courses): array
    {
        $completedAction = $this->actions->findByCode(ActionCode::Completed->value);

        $summary = [];

        foreach ($courses as $course) {
            $team = $this->studentCourses->accessibleTeams($user, $course->id)->first();

            if (! $team) {
                $summary[$course->id] = [
                    'total_lessons' => 0,
                    'completed_lessons' => 0,
                    'percentage' => 0.0,
                ];

                continue;
            }

            $totalLessons = $this->lessons->countPublishedByCourse($course->id);

            $completedCount = $completedAction
                ? count($this->timeline->completedLessonIds($user->id, $course->id, $team->id, $completedAction->id))
                : 0;

            $summary[$course->id] = [
                'total_lessons' => $totalLessons,
                'completed_lessons' => $completedCount,
                'percentage' => $totalLessons > 0 ? round(($completedCount / $totalLessons) * 100, 2) : 0.0,
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
    public function continueWatching(User $user): array
    {
        $completedAction = $this->actions->findByCode(ActionCode::Completed->value);

        if (! $completedAction) {
            return [];
        }

        $shelf = [];

        foreach ($this->studentCourses->enrolledCourses($user) as $course) {
            $team = $this->studentCourses->accessibleTeams($user, $course->id)->first();

            if (! $team) {
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
                'module_name' => $target['lesson']->topic?->module?->name,
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
