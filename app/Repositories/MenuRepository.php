<?php

namespace App\Repositories;

use App\Models\Menu;

class MenuRepository extends BaseRepository
{
    protected array $searchable = ['name', 'sub'];

    protected string $defaultSort = 'sort_order';

    protected string $defaultDirection = 'asc';

    public function __construct()
    {
        parent::__construct(new Menu);
    }
}
