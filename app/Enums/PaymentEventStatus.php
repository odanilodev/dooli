<?php

namespace App\Enums;

enum PaymentEventStatus: string
{
    case Received = 'received';
    case Processed = 'processed';
    case Failed = 'failed';
    case Duplicated = 'duplicated';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
