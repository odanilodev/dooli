<?php

namespace App\Repositories;

use App\Models\Action;

class ActionRepository extends BaseRepository
{
    protected array $searchable = ['name', 'code'];

    protected array $filterable = ['status', 'code', 'company_id'];

    public function __construct()
    {
        parent::__construct(new Action);
    }

    public function findByCode(string $code): ?Action
    {
        return $this->query()->where('code', $code)->first();
    }
}
