<?php

namespace Database\Seeders;

use App\Enums\CouponType;
use App\Enums\GeneralStatus;
use App\Models\Company;
use App\Models\Coupon;
use App\Models\StoreProduct;
use Illuminate\Database\Seeder;

class CouponSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->findOrFail(1);

        $coupons = [
            [
                'code' => 'BEMVINDO10',
                'type' => CouponType::Percent->value,
                'value' => 10,
                'usage_limit' => 100,
                'per_user_limit' => 1,
                'min_order_amount' => 50,
                'max_discount' => 50,
                'store_products' => ['curso-de-php', 'curso-de-php-anual'],
            ],
            [
                'code' => 'PHP50',
                'type' => CouponType::Fixed->value,
                'value' => 50,
                'usage_limit' => null,
                'per_user_limit' => null,
                'min_order_amount' => 100,
                'max_discount' => null,
                'store_products' => ['curso-de-php'],
            ],
        ];

        foreach ($coupons as $data) {
            $coupon = Coupon::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => $data['code']],
                [
                    'type' => $data['type'],
                    'value' => $data['value'],
                    'status' => GeneralStatus::Active->value,
                    'starts_at' => now()->subDay(),
                    'expires_at' => now()->addDays(30),
                    'usage_limit' => $data['usage_limit'],
                    'usage_count' => 0,
                    'per_user_limit' => $data['per_user_limit'],
                    'min_order_amount' => $data['min_order_amount'],
                    'max_discount' => $data['max_discount'],
                ],
            );

            $productIds = StoreProduct::query()
                ->where('company_id', $company->id)
                ->whereIn('slug', $data['store_products'])
                ->pluck('id')
                ->all();

            $coupon->storeProducts()->syncWithoutDetaching(
                collect($productIds)->mapWithKeys(fn (int $id) => [
                    $id => ['company_id' => $company->id],
                ])->all(),
            );
        }
    }
}
