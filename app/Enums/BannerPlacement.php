<?php

namespace App\Enums;

enum BannerPlacement: string
{
    case Home = 'home';
    case Store = 'store';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
