<?php

namespace App\Repositories;

use App\Models\LiveClass;

class LiveClassRepository extends BaseRepository
{
    /** @var array<int, string> */
    protected array $searchable = ['name', 'description'];

    /** @var array<int, string> */
    protected array $filterable = ['status', 'company_id'];

    protected string $defaultSort = 'starts_at';

    protected string $defaultDirection = 'desc';

    public function __construct()
    {
        parent::__construct(new LiveClass);
    }

    public function findWithRelations(int $id): LiveClass
    {
        return $this->query()->with('teams')->findOrFail($id);
    }
}
