<?php

namespace App\Console\Commands;

use App\Enums\GeneralStatus;
use App\Enums\LessonContentType;
use App\Models\Company;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\StoreProduct;
use App\Models\Topic;
use App\Models\Video;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateTestCourses extends Command
{
    protected $signature = 'courses:create-test
        {--company= : ID da empresa}
        {--domain= : Domínio da empresa (alternativa ao --company), ex.: deltaclass.com.br}
        {--video= : ID de um vídeo já existente, usado em todas as aulas (padrão: primeiro vídeo ativo da empresa)}
        {--store : Também cria um produto na loja para cada curso (inativo, a menos que use --publish)}
        {--publish : Com --store, deixa os produtos ativos}
        {--force : Com --purge, não pede confirmação}
        {--purge : Remove os cursos deste catálogo (identificados pelo nome exato)}
        {--dry-run : Mostra o que seria feito sem gravar nada}';

    protected $description = 'Cria cursos de trade de exemplo reaproveitando um vídeo existente. Idempotente.';

    /** @return array<int, array{name: string, description: string, tags: list<string>, modules: array<string, array<string, list<string>>>}> */
    private function catalog(): array
    {
        return [
            [
                'name' => 'Day Trade do Zero',
                'description' => 'Aprenda a operar com método desde o primeiro dia: mercado, plataforma, ordens e um plano de trade para chamar de seu.',
                'tags' => ['day trade', 'iniciante'],
                'modules' => [
                    'Primeiros passos' => [
                        'Introdução' => ['Boas-vindas', 'Como funciona o mercado'],
                        'Plataforma' => ['Conhecendo o gráfico', 'Tipos de ordem'],
                    ],
                    'Operando na prática' => [
                        'Setup básico' => ['Montando seu plano de trade', 'Primeira operação na simulação'],
                    ],
                ],
            ],
            [
                'name' => 'Gestão de Risco na Prática',
                'description' => 'Stop, alvo e tamanho de posição: como proteger o capital e sobreviver no longo prazo.',
                'tags' => ['risco', 'gerenciamento'],
                'modules' => [
                    'Fundamentos de risco' => [
                        'Conceitos' => ['O que é risco', 'Risco x retorno'],
                    ],
                    'Aplicação' => [
                        'Stop e alvo' => ['Calculando o stop', 'Definindo o alvo'],
                        'Tamanho de posição' => ['Quanto operar por trade'],
                    ],
                ],
            ],
            [
                'name' => 'Price Action Essencial',
                'description' => 'Leia o preço sem poluição: candles, suporte e resistência, estrutura e tendência.',
                'tags' => ['price action', 'análise técnica'],
                'modules' => [
                    'Leitura de gráfico' => [
                        'Candles' => ['Anatomia do candle', 'Padrões de candle'],
                        'Estrutura' => ['Suporte e resistência', 'Tendências e correções'],
                    ],
                ],
            ],
            [
                'name' => 'Mini Índice e Mini Dólar',
                'description' => 'Os dois contratos mais negociados da B3: horários, comportamento e estratégias de entrada.',
                'tags' => ['mini índice', 'mini dólar', 'b3'],
                'modules' => [
                    'Conhecendo os contratos' => [
                        'Mini Índice' => ['Como o WIN se move', 'Melhores horários para operar'],
                        'Mini Dólar' => ['Como o WDO se move', 'Correlações com o mercado'],
                    ],
                    'Estratégias' => [
                        'Entradas' => ['Rompimento', 'Pullback'],
                    ],
                ],
            ],
            [
                'name' => 'Psicologia do Trader',
                'description' => 'Disciplina, controle emocional e rotina: o lado mental que separa quem opera de quem apenas aposta.',
                'tags' => ['psicologia', 'disciplina'],
                'modules' => [
                    'Mentalidade' => [
                        'Disciplina' => ['Rotina de um trader', 'Lidando com o loss'],
                        'Emoções' => ['Overtrading e revenge trade'],
                    ],
                ],
            ],
            [
                'name' => 'Mesa Delta: Operações ao Vivo',
                'description' => 'Acompanhe a mesa em tempo real: leitura de mercado, tomada de decisão e revisão do dia.',
                'tags' => ['ao vivo', 'mesa'],
                'modules' => [
                    'Ao vivo' => [
                        'Sessões' => ['Sessão 1: abertura do mercado', 'Sessão 2: revisão do dia'],
                    ],
                ],
            ],
        ];
    }

    public function handle(): int
    {
        $domain = Company::normalizeDomain($this->option('domain'));

        $company = $domain
            ? Company::query()->where('domain', $domain)->first()
            : Company::query()->find((int) $this->option('company'));

        if (! $company) {
            $this->error('Empresa não encontrada. Informe --company=ID ou --domain=dominio.com.br.');

            return self::FAILURE;
        }

        if ($this->option('purge')) {
            return $this->purge($company);
        }

        $videos = Video::query()->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->orderBy('id');

        $video = $this->option('video')
            ? $videos->find((int) $this->option('video'))
            : $videos->where('status', GeneralStatus::Active->value)->first();

        if (! $video) {
            $this->error('Vídeo não encontrado nessa empresa. Cadastre um vídeo ou informe --video=ID.');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');
        $this->info(($dry ? '[dry-run] ' : '')."Empresa #{$company->id} ({$company->name}), vídeo #{$video->id} ({$video->title})");

        $run = function () use ($company, $video, $dry) {
            foreach ($this->catalog() as $i => $data) {
                $this->createCourse($company, $video, $data, $i + 1, $dry);
            }
        };

        $dry ? $run() : DB::transaction($run);

        $this->info('Pronto.');

        return self::SUCCESS;
    }

    private function createCourse(Company $company, Video $video, array $data, int $order, bool $dry): void
    {
        $companyId = $company->id;
        $name = $data['name'];
        $lessons = collect($data['modules'])->flatten(2)->count();

        $this->line("  • {$name} ({$lessons} aulas)");

        if ($dry) {
            return;
        }

        $course = Course::query()->withoutGlobalScopes()->updateOrCreate(
            ['company_id' => $companyId, 'name' => $name],
            [
                'description' => $data['description'],
                'status' => GeneralStatus::Active->value,
                'sort_order' => 900 + $order,
            ],
        );

        $moduleSort = 0;

        foreach ($data['modules'] as $moduleName => $topics) {
            $module = Module::query()->withoutGlobalScopes()->updateOrCreate(
                ['company_id' => $companyId, 'name' => $moduleName.' · '.$name],
                ['description' => $moduleName.' do curso '.$name.'.', 'status' => GeneralStatus::Active->value],
            );

            $course->modules()->syncWithoutDetaching([
                $module->id => [
                    'company_id' => $companyId,
                    'status' => GeneralStatus::Active->value,
                    'sort_order' => ++$moduleSort,
                ],
            ]);

            $topicSort = 0;

            foreach ($topics as $topicName => $lessonNames) {
                $topic = Topic::query()->withoutGlobalScopes()->updateOrCreate(
                    ['company_id' => $companyId, 'module_id' => $module->id, 'name' => $topicName],
                    ['sort_order' => ++$topicSort, 'status' => GeneralStatus::Active->value],
                );

                foreach (array_values($lessonNames) as $index => $lessonName) {
                    Lesson::query()->withoutGlobalScopes()->updateOrCreate(
                        ['company_id' => $companyId, 'topic_id' => $topic->id, 'name' => $lessonName],
                        [
                            'module_id' => $module->id,
                            'description' => $lessonName.' — '.$name.'.',
                            'content' => '<p>'.e($lessonName).'</p>',
                            'sort_order' => $index + 1,
                            'status' => GeneralStatus::Active->value,
                            'content_type' => LessonContentType::Video->value,
                            'video_id' => $video->id,
                            'published_at' => now(),
                        ],
                    );
                }
            }
        }

        if ($this->option('store')) {
            StoreProduct::query()->withoutGlobalScopes()->updateOrCreate(
                ['company_id' => $companyId, 'slug' => Str::slug($name)],
                [
                    'course_id' => $course->id,
                    'title' => $name,
                    'description' => $data['description'],
                    'price' => 97.00,
                    'sale' => 0,
                    'status' => $this->option('publish') ? GeneralStatus::Active->value : GeneralStatus::Inactive->value,
                    'tags' => $data['tags'],
                ],
            );
        }
    }

    private function purge(Company $company): int
    {
        $courses = Course::query()->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereIn('name', array_column($this->catalog(), 'name'))
            ->get();

        if ($courses->isEmpty()) {
            $this->info('Nenhum curso deste catálogo encontrado.');

            return self::SUCCESS;
        }

        $this->table(['ID', 'Curso'], $courses->map(fn ($c) => [$c->id, $c->name])->all());
        $this->warn('Atenção: se algum desses cursos já tinha alunos/vendas reais, NÃO remova.');

        if ($this->option('dry-run') || (! $this->option('force') && ! $this->confirm('Remover esses cursos e seus módulos/aulas?'))) {
            return self::SUCCESS;
        }

        DB::transaction(function () use ($courses) {
            $moduleIds = DB::table('course_modules')->whereIn('course_id', $courses->pluck('id'))->pluck('module_id');

            Lesson::query()->withoutGlobalScopes()->whereIn('module_id', $moduleIds)->get()->each->delete();
            Topic::query()->withoutGlobalScopes()->whereIn('module_id', $moduleIds)->delete();

            StoreProduct::query()->withoutGlobalScopes()->whereIn('course_id', $courses->pluck('id'))->each(function ($p) {
                $p->teams()->detach();
                $p->delete();
            });

            $courses->each(fn (Course $c) => $c->modules()->detach());
            $courses->each->delete();
            Module::query()->withoutGlobalScopes()->whereIn('id', $moduleIds)->delete();
        });

        $this->info('Cursos removidos.');

        return self::SUCCESS;
    }
}
