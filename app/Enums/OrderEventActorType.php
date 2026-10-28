<?php

namespace App\Enums;

enum OrderEventActorType: string
{
    case System = 'system';
    case Student = 'student';
    case Admin = 'admin';
    case Gateway = 'gateway';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
