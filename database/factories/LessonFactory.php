<?php

namespace Database\Factories;

use App\Enums\GeneralStatus;
use App\Models\Company;
use App\Models\Lesson;
use App\Models\Topic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lesson>
 */
class LessonFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'topic_id' => Topic::factory(),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'status' => GeneralStatus::Active->value,
            'sort_order' => 0,
        ];
    }
}
