<?php

namespace App\Repositories;

use App\Models\Attachment;

class AttachmentRepository extends BaseRepository
{
    protected array $filterable = ['status', 'company_id'];

    public function __construct()
    {
        parent::__construct(new Attachment);
    }
}
