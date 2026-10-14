<?php

namespace App\Services;

use App\Exceptions\PrerequisiteConflictException;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Topic;
use App\Repositories\TopicRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class TopicService
{
    public function __construct(
        private readonly TopicRepository $topics,
        private readonly DatalogService $audit,
        private readonly LessonService $lessons,
        private readonly LessonOrderService $orders,
    ) {}

    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->topics->paginate(
            perPage: $perPage,
            filters: $filters,
            sort: $filters['sort'] ?? null,
            direction: $filters['direction'] ?? 'asc',
        );
    }

    public function find(int $id): Topic
    {
        return $this->topics->findWithLessons($id);
    }

    public function create(array $data): Topic
    {
        $topic = $this->topics->create($data);

        $this->audit->log('topic', 'create', 'topic', "Tópico criado: {$topic->name}");

        return $topic;
    }

    public function update(Topic $topic, array $data): Topic
    {
        return $this->topics->update($topic, $data);
    }

    public function delete(Topic $topic): void
    {
        DB::transaction(function () use ($topic) {
            $topic->lessons()->get()->each(fn (Lesson $lesson) => $this->lessons->delete($lesson));
            $topic->delete();
        });

        $this->audit->log('topic', 'delete', 'topic', "Tópico excluído: {$topic->name}");
    }

    /**
     * @param  array<int, int>  $topicIds
     * @return array<int, int> lesson ids whose prerequisite lock was removed
     */
    public function reorder(Module $module, array $topicIds, bool $force = false): array
    {
        return DB::transaction(function () use ($module, $topicIds, $force) {
            foreach ($topicIds as $position => $topicId) {
                $module->topics()->whereKey($topicId)->update(['sort_order' => $position]);
            }

            $module->loadMissing('courses');
            $courseIds = $module->courses->pluck('id')->all();

            $conflicts = $this->orders->mergeConflicts(
                $this->orders->conflictsForCourses($courseIds),
                $this->orders->conflictsForModule($module->id),
            );

            if ($conflicts !== [] && ! $force) {
                throw new PrerequisiteConflictException($conflicts);
            }

            $cleared = $this->orders->clearPrerequisites(array_column($conflicts, 'lesson_id'));

            $this->audit->log('module', 'reorder_topics', 'module', "Tópicos reordenados no módulo {$module->name}");

            return $cleared;
        });
    }
}
