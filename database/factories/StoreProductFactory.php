<?php

namespace Database\Factories;

use App\Enums\GeneralStatus;
use App\Models\Company;
use App\Models\Course;
use App\Models\StoreProduct;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<StoreProduct>
 */
class StoreProductFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->words(3, true);

        return [
            'company_id' => Company::factory(),
            'course_id' => Course::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 99999),
            'description' => fake()->sentence(),
            'price' => fake()->randomFloat(2, 10, 500),
            'sale' => 0,
            'status' => GeneralStatus::Active->value,
        ];
    }
}
