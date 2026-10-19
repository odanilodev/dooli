<?php

namespace App\Enums;

enum BillingType: string
{
    case OneTime = 'one_time';
    case Subscription = 'subscription';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
