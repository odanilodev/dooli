<?php

namespace App\Services\MarketData\Providers;

/** B3 via Yahoo Finance (sufixo .SA): alternativa sem chave quando não há token da Brapi. */
class YahooFinanceB3Provider extends YahooFinanceProvider
{
    public function market(): string
    {
        return 'b3';
    }

    protected function ticker(string $symbol): string
    {
        return "{$symbol}.SA";
    }
}
