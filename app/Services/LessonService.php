<?php

namespace App\Services;

use App\Enums\GeneralStatus;
use App\Enums\LessonContentType;
use App\Events\CourseContentChanged;
use App\Exceptions\PrerequisiteConflictException;
use App\Models\Lesson;
use App\Models\Module;
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
                $data['sort_order'] = $this->nextSortOrder($data);
            }

            $lesson = $this->lessons->create($this->normalizeContent(null, $data));

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

            $lesson = $this->lessons->update($lesson, $this->normalizeContent($lesson, $this->normalizePlacement($lesson, $data)));

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
                Lesson::query()
                    ->whereKey($lessonId)
                    ->where('module_id', $topic->module_id)
                    ->update(['topic_id' => $topic->id, 'sort_order' => $position]);
            }

            $topic->loadMissing('module.courses');
            $courseIds = $topic->module?->courses?->pluck('id')->all() ?? [];

            $conflicts = $this->orders->mergeConflicts(
                $this->orders->conflictsForCourses($courseIds),
                $this->orders->conflictsForModule($topic->module_id),
            );

            if ($conflicts !== [] && ! $force) {
                throw new PrerequisiteConflictException($conflicts);
            }

            $cleared = $this->orders->clearPrerequisites(array_column($conflicts, 'lesson_id'));

            $this->audit->log('lesson', 'reorder', 'topic', "Aulas reordenadas no tópico {$topic->name}");

            return $cleared;
        });
    }

    /**
     * Reorders the lessons attached directly to a module (without a topic).
     *
     * @param  array<int, int>  $lessonIds
     * @return array<int, int> lesson ids whose prerequisite lock was removed
     */
    public function reorderModuleLessons(Module $module, array $lessonIds, bool $force = false): array
    {
        return DB::transaction(function () use ($module, $lessonIds, $force) {
            foreach ($lessonIds as $position => $lessonId) {
                Lesson::query()
                    ->whereKey($lessonId)
                    ->where('module_id', $module->id)
                    ->update(['topic_id' => null, 'sort_order' => $position]);
            }

            $module->loadMissing('courses');
            $courseIds = $module->courses?->pluck('id')->all() ?? [];

            $conflicts = $this->orders->mergeConflicts(
                $this->orders->conflictsForCourses($courseIds),
                $this->orders->conflictsForModule($module->id),
            );

            if ($conflicts !== [] && ! $force) {
                throw new PrerequisiteConflictException($conflicts);
            }

            $cleared = $this->orders->clearPrerequisites(array_column($conflicts, 'lesson_id'));

            $this->audit->log('lesson', 'reorder', 'module', "Aulas reordenadas no módulo {$module->name}");

            return $cleared;
        });
    }

    /**
     * Keeps module_id and topic_id consistent when a lesson changes placement.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizePlacement(Lesson $lesson, array $data): array
    {
        $hasTopic = array_key_exists('topic_id', $data);
        $hasModule = array_key_exists('module_id', $data);

        if ($hasTopic && $data['topic_id'] !== null) {
            $topicModuleId = Topic::query()->whereKey($data['topic_id'])->value('module_id');

            if ($topicModuleId !== null) {
                $data['module_id'] = $topicModuleId;
            }
        } elseif ($hasModule && ! $hasTopic && $lesson->topic_id !== null) {
            $currentTopicModuleId = Topic::query()->whereKey($lesson->topic_id)->value('module_id');

            if ((int) $currentTopicModuleId !== (int) $data['module_id']) {
                $data['topic_id'] = null;
            }
        }

        return $data;
    }

    /**
     * Keeps the lesson's main content source consistent with its type: the
     * chosen type wins and the fields of the other types are cleared.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeContent(?Lesson $lesson, array $data): array
    {
        $hasType = array_key_exists('content_type', $data);

        $raw = $hasType ? $data['content_type'] : $lesson?->content_type;
        $type = $raw instanceof LessonContentType ? $raw->value : $raw;

        if (! $hasType && $type === null) {
            $type = $this->deriveContentType($data);

            if ($type === null) {
                return $data;
            }
        }

        $data['content_type'] = $type;

        $previous = $lesson?->content_type?->value;

        if ($type !== null && $type !== $previous) {
            $data['content'] = null;
        }

        if ($type === null) {
            $data['video_id'] = null;
            $data['document_id'] = null;
            $data['webinar_link'] = null;
            $data['require_full_watch'] = false;

            return $data;
        }

        if ($type === LessonContentType::Video->value) {
            $data['document_id'] = null;
            $data['webinar_link'] = null;
        } elseif ($type === LessonContentType::Document->value) {
            $data['video_id'] = null;
            $data['webinar_link'] = null;
            $data['require_full_watch'] = false;
        } elseif ($type === LessonContentType::Live->value) {
            $data['video_id'] = null;
            $data['document_id'] = null;
            $data['require_full_watch'] = false;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function deriveContentType(array $data): ?string
    {
        if (! empty($data['video_id'])) {
            return LessonContentType::Video->value;
        }

        if (! empty($data['document_id'])) {
            return LessonContentType::Document->value;
        }

        if (! empty($data['webinar_link'])) {
            return LessonContentType::Live->value;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function nextSortOrder(array $data): int
    {
        $query = Lesson::query()->where('module_id', (int) $data['module_id']);

        if (! empty($data['topic_id'])) {
            $query->where('topic_id', (int) $data['topic_id']);
        } else {
            $query->whereNull('topic_id');
        }

        return (int) $query->max('sort_order') + 1;
    }

    private function dispatchContentChange(Lesson $lesson): void
    {
        $lesson->loadMissing('module.courses');

        $courseIds = $lesson->module?->courses?->pluck('id')->all() ?? [];

        if (! empty($courseIds)) {
            CourseContentChanged::dispatch($courseIds, 'lesson_created');
        }
    }
}
