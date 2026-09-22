<?php

namespace App\Repositories;

use App\Models\Topic;

class TopicRepository extends BaseRepository
{
    protected array $searchable = ['name'];

    protected array $filterable = ['status', 'module_id', 'company_id'];

    protected string $defaultSort = 'sort_order';

    protected string $defaultDirection = 'asc';

    public function __construct()
    {
        parent::__construct(new Topic);
    }

    public function findWithLessons(int $id): Topic
    {
        return $this->query()->with(['lessons.attachments'])->findOrFail($id);
    }
}
