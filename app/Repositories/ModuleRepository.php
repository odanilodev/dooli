<?php

namespace App\Repositories;

use App\Models\Module;

class ModuleRepository extends BaseRepository
{
    protected array $searchable = ['name', 'description'];

    protected array $filterable = ['status', 'company_id'];

    protected string $defaultSort = 'id';

    public function __construct()
    {
        parent::__construct(new Module);
    }

    public function findWithContent(int $id): Module
    {
        return $this->query()
            ->with([
                'topics.lessons.video', 'topics.lessons.document', 'topics.lessons.attachments',
                'looseLessons.video', 'looseLessons.document', 'looseLessons.attachments',
            ])
            ->findOrFail($id);
    }
}
