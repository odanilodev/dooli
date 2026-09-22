<?php

namespace Database\Seeders;

use App\Enums\GeneralStatus;
use App\Models\Banner;
use App\Models\Company;
use Illuminate\Database\Seeder;

class BannerSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->findOrFail(1);

        $banners = [
            [
                'name' => 'Banner Principal',
                'path' => 'banners/principal.jpg',
                'link' => '/courses',
                'sort_order' => 1,
            ],
            [
                'name' => 'Banner Promoções',
                'path' => 'banners/promocoes.jpg',
                'link' => '/store',
                'sort_order' => 2,
            ],
        ];

        foreach ($banners as $banner) {
            Banner::query()->updateOrCreate(
                ['company_id' => $company->id, 'name' => $banner['name']],
                [
                    'path' => $banner['path'],
                    'link' => $banner['link'],
                    'sort_order' => $banner['sort_order'],
                    'status' => GeneralStatus::Active->value,
                ],
            );
        }
    }
}
