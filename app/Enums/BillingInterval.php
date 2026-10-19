<?php

namespace App\Enums;

enum BillingInterval: string
{
    case Monthly = 'month';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
