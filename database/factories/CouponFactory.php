<?php

namespace Database\Factories;

use App\Enums\CouponType;
use App\Enums\GeneralStatus;
use App\Models\Company;
use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'code' => strtoupper(fake()->unique()->bothify('CUPOM####')),
            'type' => CouponType::Fixed->value,
            'value' => 10,
            'status' => GeneralStatus::Active->value,
            'usage_count' => 0,
        ];
    }
}
