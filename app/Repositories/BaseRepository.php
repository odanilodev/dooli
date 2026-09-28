<?php

namespace App\Repositories;

use App\Repositories\Contracts\RepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

abstract class BaseRepository implements RepositoryInterface
{
    /** @var array<int, string> */
    protected array $searchable = [];

    /** @var array<int, string> */
    protected array $filterable = [];

    protected string $defaultSort = 'id';

    protected string $defaultDirection = 'desc';

    public function __construct(protected Model $model) {}

    public function query(): Builder
    {
        return $this->model->newQuery();
    }

    public function paginate(
        int $perPage = 20,
        array $filters = [],
        array $with = [],
        mixed $sort = null,
        mixed $direction = 'desc',
        array $withCount = [],
    ): LengthAwarePaginator {
        $query = $this->query()->with($with)->withCount($withCount);

        $this->applyFilters($query, $filters);

        $sort = is_string($sort) && $sort !== '' ? $sort : $this->defaultSort;
        $direction = is_string($direction) && strtolower($direction) === 'asc' ? 'asc' : 'desc';

        if (in_array($sort, $this->filterable, true) || in_array($sort, $this->searchable, true) || $sort === 'id' || $sort === 'created_at' || $sort === 'sort_order') {
            $query->orderBy($this->model->qualifyColumn($sort), $direction);
        } else {
            $query->orderBy($this->model->qualifyColumn($this->defaultSort), $this->defaultDirection);
        }

        return $query->paginate($perPage);
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        foreach ($filters as $key => $value) {
            if ($value === null || $value === '' || ! is_scalar($value)) {
                continue;
            }

            if ($key === 'search' && ! empty($this->searchable)) {
                $query->where(function (Builder $q) use ($value) {
                    foreach ($this->searchable as $column) {
                        $q->orWhere($this->model->qualifyColumn($column), 'like', '%'.$value.'%');
                    }
                });

                continue;
            }

            if ($key === 'date_from') {
                $query->whereDate($this->model->qualifyColumn('created_at'), '>=', $value);

                continue;
            }

            if ($key === 'date_to') {
                $query->whereDate($this->model->qualifyColumn('created_at'), '<=', $value);

                continue;
            }

            if (in_array($key, $this->filterable, true)) {
                $query->where($this->model->qualifyColumn($key), $value);
            }
        }
    }

    public function findOrFail(int $id, array $with = []): Model
    {
        return $this->query()->with($with)->findOrFail($id);
    }

    public function create(array $data): Model
    {
        return $this->model->newInstance()->create($data);
    }

    public function update(Model $model, array $data): Model
    {
        $model->fill($data)->save();

        return $model->refresh();
    }

    public function delete(Model $model): bool
    {
        return (bool) $model->delete();
    }
}
