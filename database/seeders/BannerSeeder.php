<?php

namespace Database\Seeders;

use App\Enums\GeneralStatus;
use App\Models\Banner;
use App\Models\Company;
use Illuminate\Database\Seeder;

class BannerSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->findOrFail(1);

        $banners = [
            [
                'name' => 'Banner Principal',
                'path' => 'banners/principal.jpg',
                'link' => '/cursos',
                'sort_order' => 1,
                'eyebrow' => 'CURSO EM DESTAQUE',
                'title' => 'Do Zero ao Trader',
                'title_highlight' => 'Consistente',
                'description' => 'Aprenda estratégias, gestão de risco e psicologia para evoluir no mercado financeiro.',
                'quote' => 'Disciplina hoje, liberdade amanhã.',
                'cta_label' => 'Continuar assistindo',
                'cta_link' => '/cursos',
                'secondary_label' => 'Ver detalhes',
                'secondary_link' => '/cursos',
            ],
            [
                'name' => 'Banner Promoções',
                'path' => 'banners/promocoes.jpg',
                'link' => '/loja',
                'sort_order' => 2,
                'eyebrow' => 'OFERTA ESPECIAL',
                'title' => 'Curso de PHP',
                'title_highlight' => 'com desconto',
                'description' => 'Domine o backend do zero com projetos práticos e condições especiais por tempo limitado.',
                'quote' => 'Invista no seu próximo nível.',
                'cta_label' => 'Ver na loja',
                'cta_link' => '/loja',
                'secondary_label' => 'Saber mais',
                'secondary_link' => '/loja',
            ],
        ];

        foreach ($banners as $banner) {
            Banner::query()->updateOrCreate(
                ['company_id' => $company->id, 'name' => $banner['name']],
                [
                    ...$banner,
                    'status' => GeneralStatus::Active->value,
                ],
            );
        }
    }
}
