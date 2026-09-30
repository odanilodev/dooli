<?php

namespace App\Enums;

enum MenuType: string
{
    case Link = 'link';
    case Section = 'section';
    case Dropdown = 'dropdown';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
