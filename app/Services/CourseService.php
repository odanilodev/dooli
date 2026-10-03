<?php

namespace App\Services;

use App\DTO\CreateCourseDTO;
use App\DTO\UpdateCourseDTO;
use App\Enums\GeneralStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Course;
use App\Repositories\CourseRepository;
use App\Support\CompanyContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CourseService
{
    public function __construct(
        private readonly CourseRepository $courses,
        private readonly DatalogService $audit,
        private readonly CompanyContext $context,
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

    public function create(CreateCourseDTO $dto, ?UploadedFile $image = null): Course
    {
        $data = $dto->toArray();

        if ($image) {
            $data['image'] = $this->storeImage($image, (int) $this->context->id());
        }

        $course = $this->courses->create($data);

        $this->audit->log('course', 'create', 'course', "Curso criado: {$course->name}");

        return $course;
    }

    public function update(Course $course, UpdateCourseDTO $dto, ?UploadedFile $image = null, bool $imageProvided = false): Course
    {
        $data = $dto->toArray();

        if ($image) {
            $this->deleteImage($course->image);
            $data['image'] = $this->storeImage($image, (int) $course->company_id);
        } elseif ($imageProvided && empty($data['image'])) {
            $this->deleteImage($course->image);
            $data['image'] = null;
        } elseif (array_key_exists('image', $data) && $course->image !== $data['image']) {
            $this->deleteImage($course->image);
        }

        $course = $this->courses->update($course, $data);

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
            $this->deleteImage($course->image);
            $course->modules()->detach();
            $course->teams()->detach();
            $course->delete();
        });

        $this->audit->log('course', 'delete', 'course', "Curso excluído: {$course->name}");
    }

    private function storeImage(UploadedFile $image, int $companyId): string
    {
        return $image->store("courses/{$companyId}", 'public');
    }

    private function deleteImage(?string $image): void
    {
        if (empty($image) || str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            return;
        }

        Storage::disk('public')->delete($image);
    }
}
