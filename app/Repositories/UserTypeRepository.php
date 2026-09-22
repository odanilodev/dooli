<?php

namespace App\Repositories;

use App\Models\UserType;

class UserTypeRepository extends BaseRepository
{
    protected array $searchable = ['name', 'code'];

    protected array $filterable = ['status', 'code', 'company_id'];

    public function __construct()
    {
        parent::__construct(new UserType);
    }
}
