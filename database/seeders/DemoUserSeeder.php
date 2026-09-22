<?php

namespace Database\Seeders;

use App\Enums\GeneralStatus;
use App\Enums\UserTypeCode;
use App\Models\Company;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->findOrFail(1);

        $adminType = UserType::query()->where('company_id', $company->id)->where('code', UserTypeCode::Admin->value)->firstOrFail();
        $instructorType = UserType::query()->where('company_id', $company->id)->where('code', UserTypeCode::Instructor->value)->firstOrFail();
        $studentType = UserType::query()->where('company_id', $company->id)->where('code', UserTypeCode::Student->value)->firstOrFail();

        User::query()->updateOrCreate(
            ['email' => 'admin@cursos.test'],
            [
                'name' => 'Administrador',
                'username' => 'admin',
                'password' => Hash::make('password'),
                'status' => GeneralStatus::Active->value,
                'company_id' => $company->id,
                'user_type_id' => $adminType->id,
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'instrutor@cursos.test'],
            [
                'name' => 'Instrutor Demo',
                'username' => 'instrutor',
                'password' => Hash::make('password'),
                'status' => GeneralStatus::Active->value,
                'company_id' => $company->id,
                'user_type_id' => $instructorType->id,
                'phone' => '+55 11 98888-0001',
                'city' => 'São Paulo',
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'aluno@cursos.test'],
            [
                'name' => 'Aluno Demo',
                'username' => 'aluno',
                'password' => Hash::make('password'),
                'status' => GeneralStatus::Active->value,
                'company_id' => $company->id,
                'user_type_id' => $studentType->id,
            ],
        );
    }
}
