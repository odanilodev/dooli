<?php

namespace Database\Seeders;

use App\Enums\ActionCode;
use App\Models\Action;
use App\Models\Comment;
use App\Models\Company;
use App\Models\Course;
use App\Models\Datalog;
use App\Models\Lesson;
use App\Models\Rating;
use App\Models\Team;
use App\Models\Timeline;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class EngagementSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->findOrFail(1);

        $student = User::query()->where('email', 'aluno@cursos.test')->firstOrFail();

        $course = Course::query()
            ->where('company_id', $company->id)
            ->where('name', 'Curso de PHP')
            ->firstOrFail();

        $team = Team::query()
            ->where('company_id', $company->id)
            ->where('name', 'Turma Demo')
            ->firstOrFail();

        $lessons = Lesson::query()
            ->where('company_id', $company->id)
            ->whereIn('name', ['Boas-vindas', 'Variáveis e tipos'])
            ->get()
            ->keyBy('name');

        $comments = [
            ['lesson' => 'Boas-vindas', 'content' => 'Excelente introdução, muito claro!'],
            ['lesson' => 'Variáveis e tipos', 'content' => 'Gostei dos exemplos práticos.'],
        ];

        foreach ($comments as $data) {
            $lesson = $lessons->get($data['lesson']);
            $module = Topic::query()->findOrFail($lesson->topic_id)->module;

            Comment::query()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'lesson_id' => $lesson->id,
                    'user_id' => $student->id,
                    'content' => $data['content'],
                ],
                [
                    'module_id' => $module->id,
                    'course_id' => $course->id,
                    'team_id' => $team->id,
                    'image' => null,
                ],
            );
        }

        $ratings = [
            ['lesson' => 'Boas-vindas', 'rating' => 5],
            ['lesson' => 'Variáveis e tipos', 'rating' => 4],
        ];

        foreach ($ratings as $data) {
            $lesson = $lessons->get($data['lesson']);
            $module = Topic::query()->findOrFail($lesson->topic_id)->module;

            Rating::query()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'lesson_id' => $lesson->id,
                    'user_id' => $student->id,
                ],
                [
                    'rating' => $data['rating'],
                    'module_id' => $module->id,
                    'course_id' => $course->id,
                    'team_id' => $team->id,
                    'image' => null,
                ],
            );
        }

        $datalogs = [
            ['module' => 'courses', 'action' => 'create', 'item' => 'Curso de PHP', 'description' => 'Curso criado pelo administrador.'],
            ['module' => 'orders', 'action' => 'update', 'item' => 'MP-DEMO-0001', 'description' => 'Pedido confirmado após pagamento.'],
        ];

        foreach ($datalogs as $data) {
            Datalog::query()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'user_id' => $student->id,
                    'module' => $data['module'],
                    'action' => $data['action'],
                    'item' => $data['item'],
                ],
                ['description' => $data['description']],
            );
        }

        $started = Action::query()->where('company_id', $company->id)->where('code', ActionCode::Started->value)->firstOrFail();
        $completed = Action::query()->where('company_id', $company->id)->where('code', ActionCode::Completed->value)->firstOrFail();

        $timeline = [
            ['lesson' => 'Boas-vindas', 'action_id' => $started->id],
            ['lesson' => 'Boas-vindas', 'action_id' => $completed->id],
            ['lesson' => 'Variáveis e tipos', 'action_id' => $started->id],
        ];

        foreach ($timeline as $data) {
            $lesson = $lessons->get($data['lesson']);
            $module = Topic::query()->findOrFail($lesson->topic_id)->module;

            Timeline::query()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'user_id' => $student->id,
                    'lesson_id' => $lesson->id,
                    'action_id' => $data['action_id'],
                ],
                [
                    'module_id' => $module->id,
                    'course_id' => $course->id,
                    'team_id' => $team->id,
                ],
            );
        }

        Artisan::call('progress:rebuild', ['--company' => $company->id]);
    }
}
