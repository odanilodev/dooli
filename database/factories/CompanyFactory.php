<?php

namespace Database\Factories;

use App\Enums\GeneralStatus;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'status' => GeneralStatus::Active->value,
        ];
    }
}
