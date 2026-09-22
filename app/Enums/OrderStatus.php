<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Canceled = 'canceled';
    case Refunded = 'refunded';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
