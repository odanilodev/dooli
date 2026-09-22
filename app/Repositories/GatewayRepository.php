<?php

namespace App\Repositories;

use App\Enums\GeneralStatus;
use App\Models\Gateway;

class GatewayRepository extends BaseRepository
{
    protected array $searchable = ['name', 'code'];

    protected array $filterable = ['status', 'code', 'company_id'];

    public function __construct()
    {
        parent::__construct(new Gateway);
    }

    public function findActiveByCode(string $code): ?Gateway
    {
        return $this->query()
            ->where('code', $code)
            ->where('status', GeneralStatus::Active->value)
            ->first();
    }
}
