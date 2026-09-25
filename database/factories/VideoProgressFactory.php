<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Team;
use App\Models\User;
use App\Models\VideoProgress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VideoProgress>
 */
class VideoProgressFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'user_id' => User::factory(),
            'lesson_id' => Lesson::factory(),
            'course_id' => Course::factory(),
            'team_id' => Team::factory(),
            'position_seconds' => 0,
            'duration' => null,
        ];
    }
}
