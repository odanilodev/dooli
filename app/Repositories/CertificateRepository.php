<?php

namespace App\Repositories;

use App\Models\Certificate;

class CertificateRepository extends BaseRepository
{
    protected array $searchable = ['name', 'title', 'version'];

    protected array $filterable = ['course_id', 'company_id'];

    public function __construct()
    {
        parent::__construct(new Certificate);
    }

    public function findForCourse(int $courseId): ?Certificate
    {
        return $this->query()->where('course_id', $courseId)->latest('id')->first();
    }
}
