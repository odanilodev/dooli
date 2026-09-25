<?php

namespace Database\Factories;

use App\Enums\GeneralStatus;
use App\Enums\VideoPlayer;
use App\Models\Company;
use App\Models\VideoPlatform;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VideoPlatform>
 */
class VideoPlatformFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => 'VdoCipher',
            'code' => VideoPlayer::VdoCipher->value,
            'status' => GeneralStatus::Active->value,
            'credentials' => [],
            'settings' => [],
        ];
    }
}
