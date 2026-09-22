<?php

namespace App\Enums;

enum UserTypeCode: string
{
    case Admin = 'admin';
    case Instructor = 'instructor';
    case Student = 'student';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
