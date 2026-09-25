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

    /**
     * First published lesson of the course (module -> topic -> lesson order)
     * whose id is not in the excluded list.
     *
     * @param  array<int, int>  $excludeIds
     */
    public function nextIncompleteForCourse(int $courseId, array $excludeIds): ?Lesson
    {
        return $this->query()
            ->select('lessons.*')
            ->with(['topic.module', 'video'])
            ->join('topics', 'topics.id', '=', 'lessons.topic_id')
            ->join('course_modules', 'course_modules.module_id', '=', 'topics.module_id')
            ->where('course_modules.course_id', $courseId)
            ->where('course_modules.status', GeneralStatus::Active->value)
            ->where('topics.status', GeneralStatus::Active->value)
            ->where('lessons.status', GeneralStatus::Active->value)
            ->when($excludeIds !== [], fn ($q) => $q->whereNotIn('lessons.id', $excludeIds))
            ->orderBy('course_modules.sort_order')
            ->orderBy('topics.sort_order')
            ->orderBy('lessons.sort_order')
            ->first();
    }
}
