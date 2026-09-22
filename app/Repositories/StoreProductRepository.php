<?php

namespace App\Repositories;

use App\Enums\GeneralStatus;
use App\Models\StoreProduct;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class StoreProductRepository extends BaseRepository
{
    protected array $searchable = ['title', 'description', 'slug'];

    protected array $filterable = ['status', 'course_id', 'company_id'];

    protected string $defaultSort = 'id';

    public function __construct()
    {
        parent::__construct(new StoreProduct);
    }

    /**
     * Public catalog: only active and already released products.
     */
    public function paginateReleased(int $perPage, array $filters = [], array $with = []): LengthAwarePaginator
    {
        $query = $this->query()->with($with)->released();

        $this->applyFilters($query, $filters);

        return $query->orderByDesc('id')->paginate($perPage);
    }

    public function findReleasedBySlug(string $slug): ?StoreProduct
    {
        return $this->query()
            ->released()
            ->where('status', GeneralStatus::Active->value)
            ->where('slug', $slug)
            ->first();
    }

    public function findBySlug(string $slug): ?StoreProduct
    {
        return $this->query()->where('slug', $slug)->first();
    }

    public function findWithTeams(int $id): StoreProduct
    {
        return $this->query()->with(['course', 'teams'])->findOrFail($id);
    }
}
