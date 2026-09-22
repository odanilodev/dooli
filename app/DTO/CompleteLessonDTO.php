<?php

namespace App\DTO;

class CompleteLessonDTO
{
    public function __construct(
        public readonly int $lessonId,
        public readonly int $courseId,
        public readonly int $teamId,
        public readonly int $moduleId,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            lessonId: (int) $data['lesson_id'],
            courseId: (int) $data['course_id'],
            teamId: (int) $data['team_id'],
            moduleId: (int) $data['module_id'],
        );
    }
}
