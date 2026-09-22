<?php

namespace Database\Factories;

use App\Enums\GeneralStatus;
use App\Enums\UserTypeCode;
use App\Models\Company;
use App\Models\UserType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserType>
 */
class UserTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->word(),
            'code' => UserTypeCode::Student->value,
            'permission_menu' => [],
            'status' => GeneralStatus::Active->value,
            'company_id' => Company::factory(),
        ];
    }
}
