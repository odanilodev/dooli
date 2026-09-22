<?php

namespace Database\Factories;

use App\Enums\ActionCode;
use App\Enums\GeneralStatus;
use App\Models\Action;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Action>
 */
class ActionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => 'Concluiu',
            'code' => ActionCode::Completed->value,
            'status' => GeneralStatus::Active->value,
        ];
    }
}
