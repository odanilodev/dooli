<?php

namespace App\Enums;

enum VideoPlayer: string
{
    case VdoCipher = 'vdocipher';
    case Vimeo = 'vimeo';
    case FastPix = 'fastpix';
    case External = 'external';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
