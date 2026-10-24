<?php

namespace App\Repositories;

use App\Models\Banner;

class BannerRepository extends BaseRepository
{
    protected array $searchable = ['name', 'title', 'eyebrow'];

    protected array $filterable = ['placement', 'status', 'company_id'];

    protected string $defaultSort = 'sort_order';

    protected string $defaultDirection = 'asc';

    public function __construct()
    {
        parent::__construct(new Banner);
    }
}
