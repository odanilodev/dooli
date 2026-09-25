<?php

namespace App\Services;

use App\DTO\SaveVideoPositionDTO;
use App\Models\User;
use App\Repositories\VideoProgressRepository;

class VideoProgressService
{
    public function __construct(
        private readonly VideoProgressRepository $progress,
        private readonly StudentCourseService $studentCourses,
        private readonly DatalogService $audit,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function save(User $user, SaveVideoPositionDTO $dto): array
    {
        $team = $this->studentCourses->assertLessonInCourse($user, $dto->courseId, $dto->lessonId, $dto->teamId);

        $progress = $this->progress->savePosition(
            userId: $user->id,
            lessonId: $dto->lessonId,
            courseId: $dto->courseId,
            teamId: $team->id,
            position: $dto->position,
            duration: $dto->duration,
        );

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
