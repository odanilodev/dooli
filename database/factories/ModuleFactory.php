<?php

namespace Database\Factories;

use App\Enums\GeneralStatus;
use App\Models\Company;
use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Module>
 */
class ModuleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'status' => GeneralStatus::Active->value,
        ];
    }
}
