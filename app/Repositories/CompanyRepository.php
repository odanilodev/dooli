<?php

namespace App\Repositories;

use App\Models\Company;

class CompanyRepository extends BaseRepository
{
    protected array $searchable = ['name'];

    protected array $filterable = ['status'];

    public function __construct()
    {
        parent::__construct(new Company);
    }
}
