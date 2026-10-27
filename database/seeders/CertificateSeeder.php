<?php

namespace Database\Seeders;

use App\Models\Certificate;
use App\Models\CertificateUser;
use App\Models\Company;
use App\Models\Course;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class CertificateSeeder extends Seeder
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

        $certificate = Certificate::query()->updateOrCreate(
            ['company_id' => $company->id, 'course_id' => $course->id, 'name' => 'Certificado de Conclusão'],
            [
                'title' => 'Certificado de Conclusão do Curso de PHP',
                'version' => '1.0',
                'time' => 1800,
            ],
        );

        CertificateUser::query()->updateOrCreate(
            ['company_id' => $company->id, 'user_id' => $student->id, 'certificate_id' => $certificate->id],
            [
                'code' => 'DEMO-CERT-0001',
                'team_id' => $team->id,
                'lessons_count' => 4,
                'invalidated_at' => null,
                'invalidation_reason' => null,
            ],
        );
    }
}
