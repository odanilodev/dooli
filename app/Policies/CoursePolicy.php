<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;

class CoursePolicy extends BasePolicy
{
    public function publish(User $user, Course $course): bool
    {
        return $this->isAdmin($user) && $this->sameCompany($user, $course);
    }
}
