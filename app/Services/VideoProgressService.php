<?php

namespace App\Services;

use App\DTO\SaveVideoPositionDTO;
use App\Models\Lesson;
use App\Models\User;
use App\Repositories\StudentLessonProgressRepository;
use App\Repositories\VideoProgressRepository;
use Illuminate\Support\Facades\DB;

class VideoProgressService
{
    public function __construct(
        private readonly VideoProgressRepository $progress,
        private readonly StudentCourseService $studentCourses,
        private readonly DatalogService $audit,
        private readonly StudentLessonProgressRepository $snapshot,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function save(User $user, SaveVideoPositionDTO $dto): array
    {
        $team = $this->studentCourses->assertLessonInCourse($user, $dto->courseId, $dto->lessonId, $dto->teamId);

        $lesson = Lesson::query()->findOrFail($dto->lessonId);
        $this->studentCourses->assertLessonUnlocked($user, $lesson, $dto->courseId, $team->id);

        $progress = DB::transaction(function () use ($user, $dto, $lesson, $team) {
            $progress = $this->progress->savePosition(
                userId: $user->id,
                lessonId: $dto->lessonId,
                courseId: $dto->courseId,
                teamId: $team->id,
                position: $dto->position,
                duration: $dto->duration,
            );

            if ($lesson->module_id !== null) {
                $this->snapshot->markWatched(
                    $user->id,
                    $dto->lessonId,
                    $dto->courseId,
                    $team->id,
                    (int) $lesson->module_id,
                    $dto->position,
                );
            }

            return $progress;
        });

        $this->audit->log('progress', 'save_video_position', 'lesson', "Posição de vídeo salva: {$dto->position}s", $user->id);

        return [
            'lesson_id' => $progress->lesson_id,
            'position_seconds' => $progress->position_seconds,
            'duration' => $progress->duration,
        ];
    }

    public function positionFor(User $user, int $lessonId, int $courseId, int $teamId): int
    {
        return $this->progress
            ->forLesson($user->id, $lessonId, $courseId, $teamId)
            ?->position_seconds ?? 0;
    }
}
