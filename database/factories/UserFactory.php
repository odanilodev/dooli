<?php

namespace Database\Factories;

use App\Enums\GeneralStatus;
use App\Enums\UserRegistrationSource;
use App\Enums\UserTypeCode;
use App\Models\Company;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'status' => GeneralStatus::Active->value,
            'registration_source' => UserRegistrationSource::Unknown->value,
            'company_id' => Company::factory(),
            'user_type_id' => UserType::factory(),
            'phone' => fake()->optional()->phoneNumber(),
            'city' => fake()->optional()->city(),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => [
            'user_type_id' => UserType::factory()->state(['code' => UserTypeCode::Admin->value]),
        ]);
    }

    public function student(): static
    {
        return $this->state(fn () => [
            'user_type_id' => UserType::factory()->state(['code' => UserTypeCode::Student->value]),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'status' => GeneralStatus::Inactive->value,
        ]);
    }
}
