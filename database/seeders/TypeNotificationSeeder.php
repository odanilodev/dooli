<?php

namespace Database\Seeders;

use App\Enums\NotificationSlug;
use App\Models\TypeNotification;
use Illuminate\Database\Seeder;

class TypeNotificationSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            NotificationSlug::CourseCompleted->value => 'Curso concluído',
            NotificationSlug::CertificateAvailable->value => 'Certificado disponível',
            NotificationSlug::LessonUpdated->value => 'Aula atualizada',
            NotificationSlug::CertificateRecalculationRequired->value => 'Recálculo de certificado necessário',
        ];

        foreach ($types as $slug => $name) {
            TypeNotification::query()->updateOrCreate(
                ['company_id' => 1, 'slug' => $slug],
                ['name' => $name],
            );
        }
    }
}
