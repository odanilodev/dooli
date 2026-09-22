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
