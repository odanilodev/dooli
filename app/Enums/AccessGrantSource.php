<?php

namespace App\Enums;

enum AccessGrantSource: string
{
    case Order = 'order';
    case Subscription = 'subscription';
    case Manual = 'manual';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
