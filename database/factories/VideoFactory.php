<?php

namespace Database\Factories;

use App\Enums\GeneralStatus;
use App\Models\Company;
use App\Models\Video;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Video>
 */
class VideoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'title' => fake()->words(3, true),
            'time' => fake()->numberBetween(60, 3600),
            'status' => GeneralStatus::Active->value,
            'player' => 'external',
            'link' => 'https://example.com/video',
            'code' => fake()->uuid(),
        ];
    }
}
