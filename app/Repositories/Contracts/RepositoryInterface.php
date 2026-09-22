<?php

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

interface RepositoryInterface
{
    public function query(): Builder;

    public function paginate(
        int $perPage = 20,
        array $filters = [],
        array $with = [],
        ?string $sort = null,
        string $direction = 'desc',
    ): LengthAwarePaginator;

    public function findOrFail(int $id, array $with = []): Model;

    public function create(array $data): Model;

    public function update(Model $model, array $data): Model;

    public function delete(Model $model): bool;
}
