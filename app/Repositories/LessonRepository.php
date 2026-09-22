<?php

namespace App\Repositories;

use App\Enums\GeneralStatus;
use App\Models\Lesson;

class LessonRepository extends BaseRepository
{
    protected array $searchable = ['name', 'description'];

    protected array $filterable = ['status', 'topic_id', 'video_id', 'document_id', 'company_id'];

    protected string $defaultSort = 'sort_order';

    protected string $defaultDirection = 'asc';

    public function __construct()
    {
        parent::__construct(new Lesson);
    }

    public function findWithRelations(int $id): Lesson
    {
        return $this->query()
            ->with(['video', 'document', 'attachments', 'topic.module'])
            ->findOrFail($id);
    }

    public function countPublishedByCourse(int $courseId): int
    {
        return $this->query()
            ->whereHas('topic.module.courses', fn ($q) => $q->where('courses.id', $courseId))
            ->where('lessons.status', GeneralStatus::Active->value)
            ->count();
    }
}
