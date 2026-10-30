<?php

namespace Database\Factories;

use App\Enums\GeneralStatus;
use App\Models\Company;
use App\Models\Seller;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Seller>
 */
class SellerFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->name();

        return [
            'company_id' => Company::factory(),
            'name' => $name,
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('(##) #####-####'),
            'code' => Str::limit(Str::lower(Str::slug($name)), 24, '').'-'.fake()->unique()->numberBetween(1000, 9999),
            'share_token' => Str::random(Seller::KIT_TOKEN_LENGTH),
            'commission_percent' => 10,
            'status' => GeneralStatus::Active->value,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => GeneralStatus::Inactive->value]);
    }
}
