<?php

namespace App\Enums;

enum GatewayCode: string
{
    case MercadoPago = 'mercadopago';
    case Manual = 'manual';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
