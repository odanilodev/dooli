<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Robot;
use Illuminate\Database\Seeder;

class RobotSeeder extends Seeder
{
    public function run(): void
    {
        Company::query()->each(function (Company $company) {
            Robot::withoutGlobalScopes()->updateOrCreate(
                ['company_id' => $company->id, 'slug' => 'delta-screener'],
                [
                    'name' => 'DeltaStats',
                    'description' => 'Triagem estatística de setups de entrada por limiar fixo na B3, mercado americano e cripto.',
                    'market' => 'b3,us,crypto',
                    'icon' => 'chart',
                    'status' => 'active',
                    'sort_order' => 1,
                ],
            );
        });
    }
}
