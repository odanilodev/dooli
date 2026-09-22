<?php

namespace Database\Factories;

use App\Models\Certificate;
use App\Models\Company;
use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Certificate>
 */
class CertificateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'course_id' => Course::factory(),
            'name' => 'Certificado',
            'title' => 'Certificado de Conclusão',
            'grade' => 100,
            'version' => '1.0',
            'time' => 10,
        ];
    }
}
