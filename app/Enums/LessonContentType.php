<?php

namespace App\Enums;

enum LessonContentType: string
{
    case Video = 'video';
    case Document = 'document';
    case Live = 'live';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
