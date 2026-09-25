<?php

namespace App\Repositories;

use App\Enums\GeneralStatus;
use App\Models\VideoPlatform;

class VideoPlatformRepository extends BaseRepository
{
    protected array $searchable = ['name', 'code'];

    protected array $filterable = ['status', 'code', 'company_id'];

    public function __construct()
    {
        parent::__construct(new VideoPlatform);
    }

    public function findActiveByCode(string $code): ?VideoPlatform
    {
        return $this->query()
            ->where('code', $code)
            ->where('status', GeneralStatus::Active->value)
            ->first();
    }
}
