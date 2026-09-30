<?php

namespace App\Services;

use App\Enums\GeneralStatus;
use App\Events\CourseContentChanged;
use App\Exceptions\PrerequisiteConflictException;
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
        private readonly LessonOrderService $orders,
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
        return DB::transaction(function () use ($data) {
            if (! array_key_exists('sort_order', $data) || $data['sort_order'] === null) {
                $data['sort_order'] = $this->nextSortOrder((int) $data['topic_id']);
            }

            $lesson = $this->lessons->create($data);

            $this->orders->assertValidPrerequisite($lesson, $lesson->required_lesson_id);

            $this->audit->log('lesson', 'create', 'lesson', "Aula criada: {$lesson->name}");

            $this->dispatchContentChange($lesson);

            return $lesson;
        });
    }

    public function update(Lesson $lesson, array $data): Lesson
    {
        return DB::transaction(function () use ($lesson, $data) {
            $wasPublished = $lesson->status === GeneralStatus::Active->value;

            $lesson = $this->lessons->update($lesson, $data);

            $this->orders->assertValidPrerequisite($lesson, $lesson->required_lesson_id);

            $this->audit->log('lesson', 'update', 'lesson', "Aula atualizada: {$lesson->name}");

            if (! $wasPublished && $lesson->status === GeneralStatus::Active->value) {
                $this->dispatchContentChange($lesson);
            }

            return $lesson;
        });
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
            $lesson->comments()->delete();
            $lesson->ratings()->delete();
            $lesson->timeline()->delete();
            $lesson->videoProgresses()->delete();
            $lesson->delete();
        });

        $this->audit->log('lesson', 'delete', 'lesson', "Aula excluída: {$lesson->name}");
    }

    /**
     * @param  array<int, int>  $lessonIds
     * @return array<int, int> lesson ids whose prerequisite lock was removed
     */
    public function reorder(Topic $topic, array $lessonIds, bool $force = false): array
    {
        return DB::transaction(function () use ($topic, $lessonIds, $force) {
            foreach ($lessonIds as $position => $lessonId) {
                $topic->lessons()->whereKey($lessonId)->update(['sort_order' => $position]);
            }

            $topic->loadMissing('module.courses');
            $courseIds = $topic->module?->courses?->pluck('id')->all() ?? [];

            $conflicts = $this->orders->conflictsForCourses($courseIds);

            if ($conflicts !== [] && ! $force) {
                throw new PrerequisiteConflictException($conflicts);
            }

            $cleared = $this->orders->clearPrerequisites(array_column($conflicts, 'lesson_id'));

            $this->audit->log('lesson', 'reorder', 'topic', "Aulas reordenadas no tópico {$topic->name}");

            return $cleared;
        });
    }

    private function nextSortOrder(int $topicId): int
    {
        return (int) Lesson::query()->where('topic_id', $topicId)->max('sort_order') + 1;
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
