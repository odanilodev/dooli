<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Course;
use App\Models\CourseCompletion;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseCompletion>
 */
class CourseCompletionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'user_id' => User::factory(),
            'team_id' => Team::factory(),
            'course_id' => Course::factory(),
            'required_lessons' => 1,
            'lesson_ids' => [],
            'completed_at' => now(),
        ];
    }
}
