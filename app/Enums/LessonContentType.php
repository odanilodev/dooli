<?php

namespace App\Enums;

enum LessonContentType: string
{
    case Video = 'video';
    case Document = 'document';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
