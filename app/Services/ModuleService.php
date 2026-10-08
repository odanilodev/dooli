<?php

namespace App\Services;

use App\Enums\GeneralStatus;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\PrerequisiteConflictException;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Repositories\ModuleRepository;
use App\Support\CompanyContext;
use App\Support\ImageStorage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ModuleService
{
    public function __construct(
        private readonly ModuleRepository $modules,
        private readonly DatalogService $audit,
        private readonly LessonService $lessons,
        private readonly LessonOrderService $orders,
        private readonly ImageStorage $images,
        private readonly CompanyContext $context,
    ) {}

    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->modules->paginate(
            perPage: $perPage,
            filters: $filters,
            sort: $filters['sort'] ?? null,
            direction: $filters['direction'] ?? 'desc',
        );
    }

    public function find(int $id): Module
    {
        return $this->modules->findWithContent($id);
    }

    public function create(array $data, ?UploadedFile $image = null): Module
    {
        if ($image) {
            $data['image'] = $this->images->store($image, 'modules', (int) $this->context->id());
        }

        $module = $this->modules->create($data);

        $this->audit->log('module', 'create', 'module', "Módulo criado: {$module->name}");

        return $module;
    }

    public function update(Module $module, array $data, ?UploadedFile $image = null): Module
    {
        if ($image) {
            $this->images->delete($module->image);
            $data['image'] = $this->images->store($image, 'modules', (int) $module->company_id);
        }

        $module = $this->modules->update($module, $data);

        $this->audit->log('module', 'update', 'module', "Módulo atualizado: {$module->name}");

        return $module;
    }

    public function delete(Module $module): void
    {
        DB::transaction(function () use ($module) {
            $module->courses()->detach();
            $module->looseLessons()->get()->each(fn (Lesson $lesson) => $this->lessons->delete($lesson));
            $module->topics()->each(function ($topic) {
                $topic->lessons()->get()->each(fn (Lesson $lesson) => $this->lessons->delete($lesson));
                $topic->delete();
            });
            $module->delete();
        });

        $this->audit->log('module', 'delete', 'module', "Módulo excluído: {$module->name}");
    }

    public function attachToCourse(Course $course, int $moduleId, int $sortOrder = 0): void
    {
        $module = $this->modules->findOrFail($moduleId);

        $course->modules()->syncWithoutDetaching([
            $module->id => [
                'company_id' => $course->company_id,
                'status' => GeneralStatus::Active->value,
                'sort_order' => $sortOrder,
            ],
        ]);

        $this->audit->log('course', 'attach_module', 'course', "Módulo {$module->name} associado ao curso {$course->name}");
    }

    public function detachFromCourse(Course $course, int $moduleId): void
    {
        if (! $course->modules()->whereKey($moduleId)->exists()) {
            throw new BusinessRuleException('Módulo não está associado a este curso.');
        }

        $course->modules()->detach($moduleId);

        $this->audit->log('course', 'detach_module', 'course', "Módulo {$moduleId} desassociado do curso {$course->name}");
    }

    /**
     * @param  array<int, int>  $moduleIds  ordered list of module ids
     * @return array<int, int> lesson ids whose prerequisite lock was removed
     */
    public function reorder(Course $course, array $moduleIds, bool $force = false): array
    {
        return DB::transaction(function () use ($course, $moduleIds, $force) {
            foreach ($moduleIds as $position => $moduleId) {
                $course->modules()->updateExistingPivot($moduleId, ['sort_order' => $position]);
            }

            $conflicts = $this->orders->conflictsForCourses([$course->id]);

            if ($conflicts !== [] && ! $force) {
                throw new PrerequisiteConflictException($conflicts);
            }

            $cleared = $this->orders->clearPrerequisites(array_column($conflicts, 'lesson_id'));

            $this->audit->log('course', 'reorder_modules', 'course', "Módulos reordenados no curso {$course->name}");

            return $cleared;
        });
    }
}
