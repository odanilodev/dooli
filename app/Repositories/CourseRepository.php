<?php

namespace App\Repositories;

use App\Models\Course;

class CourseRepository extends BaseRepository
{
    protected array $searchable = ['name', 'description'];

    protected array $filterable = ['status', 'sort_order', 'company_id'];

    protected string $defaultSort = 'sort_order';

    protected string $defaultDirection = 'asc';

    public function __construct()
    {
        parent::__construct(new Course);
    }

    public function findWithContent(int $id): Course
    {
        return $this->query()
            ->with([
                'modules' => fn ($q) => $q->orderBy('course_modules.sort_order'),
                'modules.topics.lessons.video',
                'modules.topics.lessons.document',
                'modules.topics.lessons.attachments',
            ])
            ->findOrFail($id);
    }
}
