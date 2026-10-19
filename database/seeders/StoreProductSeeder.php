<?php

namespace Database\Seeders;

use App\Enums\GeneralStatus;
use App\Models\Company;
use App\Models\Course;
use App\Models\StoreProduct;
use App\Models\Team;
use Illuminate\Database\Seeder;

class StoreProductSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->findOrFail(1);

        $course = Course::query()->updateOrCreate(
            ['company_id' => $company->id, 'name' => 'Curso de PHP'],
            [
                'description' => 'Curso completo de PHP, do básico ao avançado.',
                'status' => GeneralStatus::Active->value,
                'sort_order' => 1,
            ],
        );

        $team = Team::query()->updateOrCreate(
            ['company_id' => $company->id, 'name' => 'Turma Demo'],
            [
                'status' => GeneralStatus::Active->value,
                'sort_order' => 1,
            ],
        );

        $storeProducts = [
            [
                'title' => 'Curso de PHP',
                'slug' => 'curso-de-php',
                'description' => 'Acesso completo ao Curso de PHP.',
                'price' => 199.90,
                'sale' => 149.90,
                'status' => GeneralStatus::Active->value,
                'tags' => ['php', 'backend'],
            ],
            [
                'title' => 'Curso de PHP - Acesso Anual',
                'slug' => 'curso-de-php-anual',
                'description' => 'Acesso anual ao Curso de PHP.',
                'price' => 999.00,
                'sale' => 0,
                'status' => GeneralStatus::Active->value,
                'tags' => ['php', 'anual'],
                'access_duration_days' => 365,
            ],
            [
                'title' => 'Curso de PHP - Promoção',
                'slug' => 'curso-de-php-promo',
                'description' => 'Produto de demonstração indisponível para venda.',
                'price' => 99.90,
                'sale' => 0,
                'status' => GeneralStatus::Inactive->value,
                'tags' => ['promo'],
            ],
        ];

        foreach ($storeProducts as $data) {
            $storeProduct = StoreProduct::query()->updateOrCreate(
                ['company_id' => $company->id, 'slug' => $data['slug']],
                [...$data, 'company_id' => $company->id, 'course_id' => $course->id],
            );

            // A purchase grants access through the linked teams (EnrollmentService).
            $storeProduct->teams()->syncWithoutDetaching([
                $team->id => [
                    'company_id' => $company->id,
                    'status' => GeneralStatus::Active->value,
                ],
            ]);
        }
    }
}
