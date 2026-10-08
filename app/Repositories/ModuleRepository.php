<?php

namespace App\Repositories;

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
