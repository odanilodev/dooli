<?php

namespace App\Enums;

enum NotificationSlug: string
{
    case CourseCompleted = 'course_completed';
    case CertificateAvailable = 'certificate_available';
    case LessonUpdated = 'lesson_updated';
    case CertificateRecalculationRequired = 'certificate_recalculation_required';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
