<?php

namespace App\Enums;

enum LessonProgressStatus: string
{
    case Started = 'started';
    case Completed = 'completed';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
