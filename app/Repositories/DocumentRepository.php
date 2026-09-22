<?php

namespace App\Repositories;

use App\Models\Document;

class DocumentRepository extends BaseRepository
{
    protected array $filterable = ['status', 'company_id'];

    public function __construct()
    {
        parent::__construct(new Document);
    }
}
