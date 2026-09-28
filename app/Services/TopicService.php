<?php

namespace App\Services;

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
     */
    public function reorder(Module $module, array $topicIds): void
    {
        DB::transaction(function () use ($module, $topicIds) {
            foreach ($topicIds as $position => $topicId) {
                $module->topics()->whereKey($topicId)->update(['sort_order' => $position]);
            }
        });

        $this->audit->log('module', 'reorder_topics', 'module', "Tópicos reordenados no módulo {$module->name}");
    }
}
