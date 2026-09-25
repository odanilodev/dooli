<?php

namespace Database\Seeders;

use App\Enums\GeneralStatus;
use App\Enums\VideoPlayer;
use App\Models\Company;
use App\Models\VideoPlatform;
use Illuminate\Database\Seeder;

class VideoPlatformSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->findOrFail(1);

        $platforms = [
            [
                'name' => 'VdoCipher',
                'code' => VideoPlayer::VdoCipher->value,
                'credentials' => [
                    'api_secret' => 'DEMO-VDOCIPHER-API-SECRET',
                ],
                'settings' => ['ttl' => 3600],
            ],
            [
                'name' => 'Panda Video',
                'code' => VideoPlayer::Panda->value,
                'credentials' => [
                    'api_key' => 'DEMO-PANDA-API-KEY',
                ],
                'settings' => [],
            ],
        ];

        foreach ($platforms as $platform) {
            VideoPlatform::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => $platform['code']],
                [
                    'name' => $platform['name'],
                    'status' => GeneralStatus::Active->value,
                    'credentials' => $platform['credentials'],
                    'settings' => $platform['settings'],
                ],
            );
        }
    }
}
