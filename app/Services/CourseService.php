<?php

namespace App\Services;

use App\DTO\CreateCourseDTO;
use App\DTO\UpdateCourseDTO;
use App\Enums\GeneralStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Course;
use App\Repositories\CourseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CourseService
{
    public function __construct(
        private readonly CourseRepository $courses,
        private readonly DatalogService $audit,
    ) {}

    public function paginate(array $filters, int $perPage = 20, array $with = []): LengthAwarePaginator
    {
        return $this->courses->paginate(
            perPage: $perPage,
            filters: $filters,
            with: $with,
            sort: $filters['sort'] ?? null,
            direction: $filters['direction'] ?? 'asc',
        );
    }

    public function find(int $id): Course
    {
        return $this->courses->findOrFail($id, ['modules']);
    }

    public function findWithContent(int $id): Course
    {
        return $this->courses->findWithContent($id);
    }

    public function create(CreateCourseDTO $dto): Course
    {
        $course = $this->courses->create($dto->toArray());

        $this->audit->log('course', 'create', 'course', "Curso criado: {$course->name}");

        return $course;
    }

    public function update(Course $course, UpdateCourseDTO $dto): Course
    {
        $course = $this->courses->update($course, $dto->toArray());

        $this->audit->log('course', 'update', 'course', "Curso atualizado: {$course->name}");

        return $course;
    }

    public function publish(Course $course): Course
    {
        return $this->setStatus($course, GeneralStatus::Active->value, 'publish');
    }

    public function unpublish(Course $course): Course
    {
        return $this->setStatus($course, GeneralStatus::Inactive->value, 'unpublish');
    }

    private function setStatus(Course $course, string $status, string $action): Course
    {
        $course = $this->courses->update($course, ['status' => $status]);

        $this->audit->log('course', $action, 'course', "Curso {$action}: {$course->name}");

        return $course;
    }

    public function delete(Course $course): void
    {
        if ($course->storeProducts()->exists()) {
            throw new BusinessRuleException('Não é possível excluir: existem produtos de loja vinculados. Desative o curso.');
        }

        if ($course->certificates()->exists()) {
            throw new BusinessRuleException('Não é possível excluir: existem certificados vinculados. Desative o curso.');
        }

        DB::transaction(function () use ($course) {
            $course->modules()->detach();
            $course->teams()->detach();
            $course->delete();
        });

        $this->audit->log('course', 'delete', 'course', "Curso excluído: {$course->name}");
    }
}
