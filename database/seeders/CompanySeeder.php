<?php

namespace Database\Seeders;

use App\Enums\GeneralStatus;
use App\Models\Company;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        Company::query()->updateOrCreate(
            ['id' => 1],
            ['name' => 'Cursos Demo', 'status' => GeneralStatus::Active->value],
        );
    }
}
