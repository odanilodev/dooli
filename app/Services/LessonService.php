<?php

namespace App\Services;

use App\Enums\GeneralStatus;
use App\Events\CourseContentChanged;
use App\Models\Lesson;
use App\Models\Topic;
use App\Repositories\LessonRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class LessonService
{
    public function __construct(
        private readonly LessonRepository $lessons,
        private readonly DatalogService $audit,
    ) {}

    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->lessons->paginate(
            perPage: $perPage,
            filters: $filters,
            with: ['video', 'document'],
            sort: $filters['sort'] ?? null,
            direction: $filters['direction'] ?? 'asc',
        );
    }

    public function find(int $id): Lesson
    {
        return $this->lessons->findWithRelations($id);
    }

    public function create(array $data): Lesson
    {
        $lesson = $this->lessons->create($data);

        $this->audit->log('lesson', 'create', 'lesson', "Aula criada: {$lesson->name}");

        $this->dispatchContentChange($lesson);

        return $lesson;
    }

    public function update(Lesson $lesson, array $data): Lesson
    {
        $wasPublished = $lesson->status === GeneralStatus::Active->value;

        $lesson = $this->lessons->update($lesson, $data);

        $this->audit->log('lesson', 'update', 'lesson', "Aula atualizada: {$lesson->name}");

        if (! $wasPublished && $lesson->status === GeneralStatus::Active->value) {
            $this->dispatchContentChange($lesson);
        }

        return $lesson;
    }

    public function publish(Lesson $lesson): Lesson
    {
        $lesson = $this->lessons->update($lesson, [
            'status' => GeneralStatus::Active->value,
            'published_at' => $lesson->published_at ?? now(),
        ]);

        $this->dispatchContentChange($lesson);

        return $lesson;
    }

    public function unpublish(Lesson $lesson): Lesson
    {
        return $this->lessons->update($lesson, ['status' => GeneralStatus::Inactive->value]);
    }

    public function delete(Lesson $lesson): void
    {
        DB::transaction(function () use ($lesson) {
            $lesson->attachments()->detach();
            $lesson->delete();
        });

        $this->audit->log('lesson', 'delete', 'lesson', "Aula excluída: {$lesson->name}");
    }

    /**
     * @param  array<int, int>  $lessonIds
     */
    public function reorder(Topic $topic, array $lessonIds): void
    {
        DB::transaction(function () use ($topic, $lessonIds) {
            foreach ($lessonIds as $position => $lessonId) {
                $topic->lessons()->whereKey($lessonId)->update(['sort_order' => $position]);
            }
        });

        $this->audit->log('lesson', 'reorder', 'topic', "Aulas reordenadas no tópico {$topic->name}");
    }

    private function dispatchContentChange(Lesson $lesson): void
    {
        $lesson->loadMissing('topic.module.courses');

        $courseIds = $lesson->topic?->module?->courses?->pluck('id')->all() ?? [];

        if (! empty($courseIds)) {
            CourseContentChanged::dispatch($courseIds, 'lesson_created');
        }
    }
}
