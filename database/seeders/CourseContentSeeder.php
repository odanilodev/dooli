<?php

namespace Database\Seeders;

use App\Enums\GeneralStatus;
use App\Enums\VideoPlayer;
use App\Models\Attachment;
use App\Models\Company;
use App\Models\Course;
use App\Models\Document;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Topic;
use App\Models\Video;
use Illuminate\Database\Seeder;

class CourseContentSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->findOrFail(1);
        $companyId = $company->id;

        $course = Course::query()
            ->where('company_id', $companyId)
            ->where('name', 'Curso de PHP')
            ->firstOrFail();

        $videos = [
            'boas-vindas' => [
                'title' => 'Boas-vindas ao curso',
                'link' => 'https://videos.cursos.test/boas-vindas',
                'time' => 300,
                'code' => 'VID001',
            ],
            'variaveis-e-tipos' => [
                'title' => 'Variáveis e tipos',
                'link' => 'https://videos.cursos.test/variaveis-e-tipos',
                'time' => 600,
                'code' => 'VID002',
            ],
            'classes-e-objetos' => [
                'title' => 'Classes e objetos',
                'link' => 'https://videos.cursos.test/classes-e-objetos',
                'time' => 900,
                'code' => 'VID003',
            ],
        ];

        $videoModels = [];

        foreach ($videos as $key => $video) {
            $videoModels[$key] = Video::query()->updateOrCreate(
                ['company_id' => $companyId, 'title' => $video['title']],
                [
                    'image' => 'videos/'.$key.'.jpg',
                    'time' => $video['time'],
                    'status' => GeneralStatus::Active->value,
                    'player' => VideoPlayer::External->value,
                    'link' => $video['link'],
                    'code' => $video['code'],
                ],
            );
        }

        $documents = [
            'apostila' => 'documents/apostila-php.pdf',
            'checklist' => 'documents/checklist-projeto.pdf',
        ];

        $documentModels = [];

        foreach ($documents as $key => $path) {
            $documentModels[$key] = Document::query()->updateOrCreate(
                ['company_id' => $companyId, 'path' => $path],
                [
                    'image' => 'documents/'.$key.'.jpg',
                    'status' => GeneralStatus::Active->value,
                ],
            );
        }

        $attachments = [
            'exercicio' => 'attachments/exercicio-01.zip',
            'slide' => 'attachments/slide-aula-01.pdf',
        ];

        $attachmentModels = [];

        foreach ($attachments as $key => $path) {
            $attachmentModels[$key] = Attachment::query()->updateOrCreate(
                ['company_id' => $companyId, 'path' => $path],
                ['status' => GeneralStatus::Active->value],
            );
        }

        $modules = [
            'fundamentos' => [
                'name' => 'Fundamentos do PHP',
                'description' => 'Primeiros passos com a linguagem PHP.',
                'topics' => [
                    [
                        'name' => 'Introdução',
                        'lessons' => [
                            ['name' => 'Boas-vindas', 'video' => 'boas-vindas', 'document' => 'apostila', 'attachments' => ['exercicio', 'slide']],
                            ['name' => 'Como instalar o PHP', 'video' => 'variaveis-e-tipos', 'document' => null, 'attachments' => []],
                        ],
                    ],
                    [
                        'name' => 'Sintaxe Básica',
                        'lessons' => [
                            ['name' => 'Variáveis e tipos', 'video' => 'variaveis-e-tipos', 'document' => 'checklist', 'attachments' => ['slide']],
                        ],
                    ],
                ],
            ],
            'avancado' => [
                'name' => 'PHP Avançado',
                'description' => 'Orientação a objetos e boas práticas.',
                'topics' => [
                    [
                        'name' => 'Orientação a Objetos',
                        'lessons' => [
                            ['name' => 'Classes e objetos', 'video' => 'classes-e-objetos', 'document' => null, 'attachments' => []],
                        ],
                    ],
                ],
            ],
        ];

        $moduleSort = 0;

        foreach ($modules as $moduleKey => $moduleData) {
            $moduleSort++;

            $module = Module::query()->updateOrCreate(
                ['company_id' => $companyId, 'name' => $moduleData['name']],
                [
                    'description' => $moduleData['description'],
                    'image' => 'modules/'.$moduleKey.'.jpg',
                    'status' => GeneralStatus::Active->value,
                ],
            );

            $course->modules()->syncWithoutDetaching([
                $module->id => [
                    'company_id' => $companyId,
                    'status' => GeneralStatus::Active->value,
                    'sort_order' => $moduleSort,
                ],
            ]);

            $topicSort = 0;

            foreach ($moduleData['topics'] as $topicData) {
                $topicSort++;

                $topic = Topic::query()->updateOrCreate(
                    ['company_id' => $companyId, 'module_id' => $module->id, 'name' => $topicData['name']],
                    [
                        'sort_order' => $topicSort,
                        'status' => GeneralStatus::Active->value,
                    ],
                );

                $lessonSort = 0;

                foreach ($topicData['lessons'] as $lessonData) {
                    $lessonSort++;

                    $lesson = Lesson::query()->updateOrCreate(
                        ['company_id' => $companyId, 'topic_id' => $topic->id, 'name' => $lessonData['name']],
                        [
                            'description' => 'Aula de demonstração: '.$lessonData['name'].'.',
                            'content' => '<p>Conteúdo da aula '.$lessonData['name'].'.</p>',
                            'sort_order' => $lessonSort,
                            'status' => GeneralStatus::Active->value,
                            'video_id' => $videoModels[$lessonData['video']]->id,
                            'document_id' => $lessonData['document'] ? $documentModels[$lessonData['document']]->id : null,
                            'published_at' => now(),
                        ],
                    );

                    foreach ($lessonData['attachments'] as $index => $attachmentKey) {
                        $lesson->attachments()->syncWithoutDetaching([
                            $attachmentModels[$attachmentKey]->id => [
                                'company_id' => $companyId,
                                'status' => GeneralStatus::Active->value,
                                'sort_order' => $index + 1,
                            ],
                        ]);
                    }
                }
            }
        }
    }
}
