<?php

namespace App\Enums;

enum RefundStatus: string
{
    case Pending = 'pending';
    case Refunded = 'refunded';
    case Failed = 'failed';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
