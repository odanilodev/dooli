<?php

namespace App\Enums;

enum ActionCode: string
{
    case Started = 'started';
    case Viewed = 'viewed';
    case Completed = 'completed';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
