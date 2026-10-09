<?php

namespace Database\Factories;

use App\Enums\GeneralStatus;
use App\Models\Company;
use App\Models\LiveClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LiveClass>
 */
class LiveClassFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'link' => 'https://meet.example.com/'.fake()->uuid(),
            'starts_at' => now()->addDay(),
            'duration' => 60,
            'status' => GeneralStatus::Active->value,
        ];
    }
}
