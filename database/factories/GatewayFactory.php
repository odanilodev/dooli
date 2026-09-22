<?php

namespace Database\Factories;

use App\Enums\GatewayCode;
use App\Enums\GeneralStatus;
use App\Models\Company;
use App\Models\Gateway;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Gateway>
 */
class GatewayFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => 'Manual',
            'code' => GatewayCode::Manual->value,
            'status' => GeneralStatus::Active->value,
            'credentials' => [],
            'settings' => [],
        ];
    }
}
