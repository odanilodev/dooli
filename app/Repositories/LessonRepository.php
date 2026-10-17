<?php

namespace App\Repositories;

use App\Enums\GeneralStatus;
use App\Models\Lesson;
use Illuminate\Database\Eloquent\Collection;

class LessonRepository extends BaseRepository
{
    protected array $searchable = ['name', 'description'];

    protected array $filterable = ['status', 'module_id', 'topic_id', 'video_id', 'document_id', 'company_id'];

    protected string $defaultSort = 'sort_order';

    protected string $defaultDirection = 'asc';

    public function __construct()
    {
        parent::__construct(new Lesson);
    }

    public function findWithRelations(int $id): Lesson
    {
        return $this->query()
            ->with(['video', 'document', 'attachments', 'module', 'topic.module'])
            ->findOrFail($id);
    }

    public function countPublishedByCourse(int $courseId): int
    {
        return (int) $this->query()
            ->join('course_modules', 'course_modules.module_id', '=', 'lessons.module_id')
            ->leftJoin('topics', 'topics.id', '=', 'lessons.topic_id')
            ->where('course_modules.course_id', $courseId)
            ->where('course_modules.status', GeneralStatus::Active->value)
            ->where('lessons.status', GeneralStatus::Active->value)
            ->where(fn ($q) => $q->whereNull('lessons.topic_id')->orWhere('topics.status', GeneralStatus::Active->value))
            ->distinct()
            ->count('lessons.id');
    }

    /**
     * Total active lessons per course id, in a single grouped query.
     *
     * @param  array<int, int>  $courseIds
     * @return array<int, int> course_id => total
     */
    public function publishedCountsForCourses(array $courseIds): array
    {
        if ($courseIds === []) {
            return [];
        }

        return $this->query()
            ->join('course_modules', 'course_modules.module_id', '=', 'lessons.module_id')
            ->leftJoin('topics', 'topics.id', '=', 'lessons.topic_id')
            ->where('lessons.status', GeneralStatus::Active->value)
            ->where('course_modules.status', GeneralStatus::Active->value)
            ->where(fn ($q) => $q->whereNull('lessons.topic_id')->orWhere('topics.status', GeneralStatus::Active->value))
            ->whereIn('course_modules.course_id', $courseIds)
            ->selectRaw('course_modules.course_id as pivot_course_id, count(distinct lessons.id) as aggregate')
            ->groupBy('course_modules.course_id')
            ->get()
            ->mapWithKeys(fn (Lesson $row) => [(int) $row->pivot_course_id => (int) $row->aggregate])
            ->all();
    }

    /**
     * Published lessons of the given courses, grouped by course id and ordered
     * the same way as nextIncompleteForCourse (module -> topic-less last ->
     * topic -> lesson).
     *
     * @param  array<int, int>  $courseIds
     * @return Collection<int, Collection<int, Lesson>> course_id => lessons
     */
    public function publishedLessonsByCourse(array $courseIds): Collection
    {
        if ($courseIds === []) {
            return new Collection;
        }

        return $this->query()
            ->select('lessons.*', 'course_modules.course_id as pivot_course_id')
            ->with(['module', 'video'])
            ->leftJoin('topics', 'topics.id', '=', 'lessons.topic_id')
            ->join('course_modules', 'course_modules.module_id', '=', 'lessons.module_id')
            ->whereIn('course_modules.course_id', $courseIds)
            ->where('course_modules.status', GeneralStatus::Active->value)
            ->where(fn ($q) => $q->whereNull('lessons.topic_id')->orWhere('topics.status', GeneralStatus::Active->value))
            ->where('lessons.status', GeneralStatus::Active->value)
            ->orderBy('course_modules.course_id')
            ->orderBy('course_modules.sort_order')
            ->orderByRaw('lessons.topic_id IS NULL')
            ->orderBy('topics.sort_order')
            ->orderBy('lessons.sort_order')
            ->get()
            ->groupBy('pivot_course_id');
    }

    /**
     * First published lesson of the course (module -> topic -> lesson order,
     * with topic-less lessons after the topics of each module) whose id is not
     * in the excluded list.
     *
     * @param  array<int, int>  $excludeIds
     */
    public function nextIncompleteForCourse(int $courseId, array $excludeIds): ?Lesson
    {
        return $this->query()
            ->select('lessons.*')
            ->with(['module', 'topic.module', 'video'])
            ->leftJoin('topics', 'topics.id', '=', 'lessons.topic_id')
            ->join('course_modules', 'course_modules.module_id', '=', 'lessons.module_id')
            ->where('course_modules.course_id', $courseId)
            ->where('course_modules.status', GeneralStatus::Active->value)
            ->where(fn ($q) => $q->whereNull('lessons.topic_id')->orWhere('topics.status', GeneralStatus::Active->value))
            ->where('lessons.status', GeneralStatus::Active->value)
            ->when($excludeIds !== [], fn ($q) => $q->whereNotIn('lessons.id', $excludeIds))
            ->orderBy('course_modules.sort_order')
            ->orderByRaw('lessons.topic_id IS NULL')
            ->orderBy('topics.sort_order')
            ->orderBy('lessons.sort_order')
            ->first();
    }
}
