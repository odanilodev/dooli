<?php

namespace Database\Seeders;

use App\Enums\GeneralStatus;
use App\Enums\NotificationSlug;
use App\Models\Company;
use App\Models\Notification;
use App\Models\Team;
use App\Models\TypeNotification;
use App\Models\User;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->findOrFail(1);

        $team = Team::query()
            ->where('company_id', $company->id)
            ->where('name', 'Turma Demo')
            ->firstOrFail();

        $student = User::query()->where('email', 'aluno@cursos.test')->firstOrFail();

        $notifications = [
            [
                'slug' => NotificationSlug::CourseCompleted->value,
                'read_at' => now()->subHour(),
            ],
            [
                'slug' => NotificationSlug::CertificateAvailable->value,
                'read_at' => null,
            ],
        ];

        foreach ($notifications as $data) {
            $type = TypeNotification::query()
                ->where('company_id', $company->id)
                ->where('slug', $data['slug'])
                ->firstOrFail();

            $notification = Notification::query()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'team_id' => $team->id,
                    'type_notification_id' => $type->id,
                ],
                [
                    'release_at' => now()->subDay(),
                    'status' => GeneralStatus::Active->value,
                ],
            );

            $notification->users()->syncWithoutDetaching([
                $student->id => [
                    'company_id' => $company->id,
                    'read_at' => $data['read_at'],
                ],
            ]);
        }
    }
}
