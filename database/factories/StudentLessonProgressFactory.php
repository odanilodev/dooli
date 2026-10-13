<?php

namespace Database\Factories;

use App\Enums\LessonProgressStatus;
use App\Models\Company;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\StudentLessonProgress;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentLessonProgress>
 */
class StudentLessonProgressFactory extends Factory
{
    protected $model = StudentLessonProgress::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'user_id' => User::factory(),
            'team_id' => Team::factory(),
            'course_id' => Course::factory(),
            'module_id' => Module::factory(),
            'lesson_id' => Lesson::factory(),
            'status' => LessonProgressStatus::Started->value,
            'started_at' => now(),
            'completed_at' => null,
            'position_seconds' => 0,
            'watched_seconds' => 0,
            'last_activity_at' => now(),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => LessonProgressStatus::Completed->value,
            'completed_at' => now(),
        ]);
    }
}
