<?php

namespace App\Enums;

enum SellerCommissionStatus: string
{
    case Approved = 'approved';
    case Paid = 'paid';
    case Reversed = 'reversed';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
