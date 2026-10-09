<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\CompanySetting;
use Illuminate\Database\Seeder;

class CompanySettingSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->findOrFail(1);

        $settings = [
            'site_name' => 'Cursos Demo',
            'support_email' => 'suporte@cursos.test',
            'support_phone' => '+55 11 4000-0000',
            'document' => '12.345.678/0001-90',
            'address' => 'Av. Paulista, 1000',
            'city' => 'São Paulo',
            'terms_url' => 'https://cursos.test/termos',
            'privacy_url' => 'https://cursos.test/privacidade',
        ];

        foreach ($settings as $name => $value) {
            CompanySetting::query()->updateOrCreate(
                ['company_id' => $company->id, 'name' => $name],
                ['value' => $value],
            );
        }
    }
}
