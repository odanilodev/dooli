<?php

namespace Database\Seeders;

use App\Enums\GeneralStatus;
use App\Enums\UserTypeCode;
use App\Models\UserType;
use Illuminate\Database\Seeder;

class UserTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            UserTypeCode::Admin->value => 'Administrador',
            UserTypeCode::Instructor->value => 'Instrutor',
            UserTypeCode::Student->value => 'Aluno',
        ];

        foreach ($types as $code => $name) {
            UserType::query()->updateOrCreate(
                ['company_id' => 1, 'code' => $code],
                ['name' => $name, 'status' => GeneralStatus::Active->value, 'permission_menu' => []],
            );
        }
    }
}
