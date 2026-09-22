<?php

namespace Database\Seeders;

use App\Enums\GeneralStatus;
use App\Models\Company;
use App\Models\Course;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoEnrollmentSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->findOrFail(1);

        $course = Course::query()
            ->where('company_id', $company->id)
            ->where('name', 'Curso de PHP')
            ->firstOrFail();

        $team = Team::query()
            ->where('company_id', $company->id)
            ->where('name', 'Turma Demo')
            ->firstOrFail();

        $student = User::query()->where('email', 'aluno@cursos.test')->firstOrFail();

        $team->courses()->syncWithoutDetaching([
            $course->id => [
                'company_id' => $company->id,
                'status' => GeneralStatus::Active->value,
            ],
        ]);

        $team->users()->syncWithoutDetaching([
            $student->id => [
                'company_id' => $company->id,
                'status' => GeneralStatus::Active->value,
                'expired_at' => null,
            ],
        ]);
    }
}
