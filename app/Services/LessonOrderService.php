<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Lesson;
use Illuminate\Support\Facades\DB;

class LessonOrderService
{
    /**
     * Ordered lesson ids of a course, following the same order the admin
     * curriculum builder shows: module pivot order -> topic order -> lesson order.
     *
     * @return array<int, int>
     */
    public function sequenceForCourse(int $courseId): array
    {
        return DB::table('lessons')
            ->leftJoin('topics', 'topics.id', '=', 'lessons.topic_id')
            ->join('course_modules', 'course_modules.module_id', '=', 'lessons.module_id')
            ->where('course_modules.course_id', $courseId)
            ->orderBy('course_modules.sort_order')
            ->orderByRaw('lessons.topic_id IS NULL')
            ->orderBy('topics.sort_order')
            ->orderBy('lessons.sort_order')
            ->orderBy('lessons.id')
            ->pluck('lessons.id')
            ->all();
    }

    /**
     * Lessons whose prerequisite is no longer earlier in the course order.
     *
     * @param  array<int, int>  $courseIds
     * @return array<int, array{lesson_id: int, lesson_name: string, required_lesson_id: int, required_lesson_name: string}>
     */
    public function conflictsForCourses(array $courseIds): array
    {
        $conflicts = [];

        foreach (array_unique($courseIds) as $courseId) {
            foreach ($this->conflictsForCourse((int) $courseId) as $conflict) {
                $conflicts[$conflict['lesson_id']] = $conflict;
            }
        }

        return array_values($conflicts);
    }

    /**
     * @return array<int, array{lesson_id: int, lesson_name: string, required_lesson_id: int, required_lesson_name: string}>
     */
    public function conflictsForCourse(int $courseId): array
    {
        $sequence = $this->sequenceForCourse($courseId);

        if ($sequence === []) {
            return [];
        }

        $position = array_flip($sequence);

        $lessons = Lesson::query()
            ->whereIn('id', $sequence)
            ->whereNotNull('required_lesson_id')
            ->get(['id', 'name', 'required_lesson_id']);

        $requiredNames = Lesson::query()
            ->whereIn('id', $lessons->pluck('required_lesson_id')->unique()->all())
            ->pluck('name', 'id');

        $conflicts = [];

        foreach ($lessons as $lesson) {
            $requiredId = (int) $lesson->required_lesson_id;

            if (! isset($position[$requiredId], $position[$lesson->id])) {
                continue;
            }

            if ($position[$requiredId] >= $position[$lesson->id]) {
                $conflicts[] = [
                    'lesson_id' => $lesson->id,
                    'lesson_name' => $lesson->name,
                    'required_lesson_id' => $requiredId,
                    'required_lesson_name' => $requiredNames[$requiredId] ?? 'Aula removida',
                ];
            }
        }

        return $conflicts;
    }

    /**
     * Removes the prerequisite lock of the given lessons.
     *
     * @param  array<int, int>  $lessonIds
     * @return array<int, int> ids whose lock was removed
     */
    public function clearPrerequisites(array $lessonIds): array
    {
        $lessonIds = array_values(array_unique($lessonIds));

        if ($lessonIds === []) {
            return [];
        }

        Lesson::query()->whereIn('id', $lessonIds)->update(['required_lesson_id' => null]);

        return $lessonIds;
    }

    /**
     * A prerequisite must be another lesson of the same course that appears
     * earlier in the course order.
     */
    public function assertValidPrerequisite(Lesson $lesson, ?int $requiredLessonId): void
    {
        if ($requiredLessonId === null) {
            return;
        }

        if ($requiredLessonId === $lesson->id) {
            throw new BusinessRuleException('Uma aula não pode depender dela mesma.');
        }

        $lesson->loadMissing('module.courses');
        $courseIds = $lesson->module?->courses?->pluck('id')->all() ?? [];

        $required = Lesson::query()->with('module.courses')->find($requiredLessonId);

        if (! $required) {
            throw new BusinessRuleException('Aula pré-requisito não encontrada.');
        }

        if ($required->company_id !== $lesson->company_id) {
            throw new BusinessRuleException('O pré-requisito precisa ser uma aula da mesma empresa.');
        }

        $requiredCourseIds = $required->module?->courses?->pluck('id')->all() ?? [];
        $sharedCourseIds = array_values(array_intersect($courseIds, $requiredCourseIds));

        if ($sharedCourseIds === []) {
            throw new BusinessRuleException('O pré-requisito precisa ser uma aula do mesmo curso.');
        }

        foreach ($sharedCourseIds as $courseId) {
            $position = array_flip($this->sequenceForCourse((int) $courseId));

            if (! isset($position[$lesson->id], $position[$requiredLessonId])) {
                continue;
            }

            if ($position[$requiredLessonId] >= $position[$lesson->id]) {
                throw new BusinessRuleException('O pré-requisito precisa ser uma aula anterior na ordem do curso.');
            }
        }
    }
}
