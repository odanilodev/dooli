<?php

namespace App\Exceptions;

use RuntimeException;

class MarketDataException extends RuntimeException
{
    public static function upstream(string $source, string $symbol, int $status): self
    {
        return new self("{$symbol}: falha ao consultar {$source} (HTTP {$status}).");
    }
}
