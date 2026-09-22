<?php

namespace App\Enums;

enum CouponType: string
{
    case Fixed = 'fixed';
    case Percent = 'percent';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
