<?php

namespace Database\Factories;

use App\Enums\GeneralStatus;
use App\Models\Company;
use App\Models\Module;
use App\Models\Topic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Topic>
 */
class TopicFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'module_id' => Module::factory(),
            'name' => fake()->words(2, true),
            'status' => GeneralStatus::Active->value,
            'sort_order' => 0,
        ];
    }
}
