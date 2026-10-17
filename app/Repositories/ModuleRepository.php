<?php

namespace App\Repositories;

use App\Enums\GeneralStatus;
use App\Models\Lesson;
use App\Models\Module;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ModuleRepository extends BaseRepository
{
    protected array $searchable = ['name', 'description'];

    protected array $filterable = ['status', 'company_id'];

    protected string $defaultSort = 'id';

    public function __construct()
    {
        parent::__construct(new Module);
    }

    public function findWithContent(int $id): Module
    {
        return $this->query()
            ->with([
                'courses',
                'topics.lessons.video', 'topics.lessons.document', 'topics.lessons.attachments',
                'looseLessons.video', 'looseLessons.document', 'looseLessons.attachments',
            ])
            ->findOrFail($id);
    }

    /**
     * Immutable snapshot of the course modules for a certificate.
     *
     * When lesson ids are provided, only modules that had completed lessons are
     * included. This keeps modules added after the student finished the course
     * out of the certificate, including retroactive issuance.
     *
     * @param  array<int, int>  $lessonIds
     * @return array<int, array{id: int, name: string}>
     */
    public function snapshotForCourse(int $courseId, array $lessonIds = []): array
    {
        $query = $this->query()
            ->select(['modules.id', 'modules.name'])
            ->join('course_modules', 'course_modules.module_id', '=', 'modules.id')
            ->where('course_modules.course_id', $courseId)
            ->where('course_modules.status', GeneralStatus::Active->value)
            ->orderBy('course_modules.sort_order')
            ->orderBy('modules.name');

        if ($lessonIds !== []) {
            $moduleIds = Lesson::query()
                ->whereIn('id', $lessonIds)
                ->whereNotNull('module_id')
                ->distinct()
                ->pluck('module_id')
                ->all();

            $query->whereIn('modules.id', $moduleIds);
        }

        return $query->get()
            ->map(fn (Module $module) => [
                'id' => (int) $module->id,
                'name' => (string) $module->name,
            ])
            ->all();
    }

    /**
     * Paginated modules of the current company with their full content tree.
     */
    public function paginateWithContent(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        return $this->paginate(
            perPage: $perPage,
            filters: $filters,
            with: [
                'courses',
                'topics.lessons.video', 'topics.lessons.document', 'topics.lessons.attachments',
                'looseLessons.video', 'looseLessons.document', 'looseLessons.attachments',
            ],
            sort: 'name',
            direction: 'asc',
        );
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        $usage = $filters['usage'] ?? null;
        unset($filters['usage']);

        if ($usage === 'used') {
            $query->whereHas('courses');
        } elseif ($usage === 'unused') {
            $query->whereDoesntHave('courses');
        }

        parent::applyFilters($query, $filters);
    }
}
