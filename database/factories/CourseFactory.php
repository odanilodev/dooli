<?php

namespace Database\Factories;

use App\Enums\GeneralStatus;
use App\Models\Company;
use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'status' => GeneralStatus::Active->value,
            'sort_order' => 0,
        ];
    }
}
