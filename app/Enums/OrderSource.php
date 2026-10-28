<?php

namespace App\Enums;

enum OrderSource: string
{
    case Store = 'store';
    case Manual = 'manual';
    case Unknown = 'unknown';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
