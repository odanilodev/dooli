<?php

namespace App\Repositories;

use App\Models\Video;

class VideoRepository extends BaseRepository
{
    protected array $searchable = ['title', 'code'];

    protected array $filterable = ['status', 'player', 'company_id'];

    public function __construct()
    {
        parent::__construct(new Video);
    }
}
