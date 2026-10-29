<?php

namespace App\Services\MarketData\Contracts;

use App\Exceptions\MarketDataException;
use Carbon\CarbonInterface;

interface IntradayProvider
{
    /**
     * Candles de 15 minutos após $lastTs (janela inicial de config('market.intraday_backfill_days') quando null).
     * `ts` em UTC, formato Y-m-d H:i:s.
     *
     * @return array<int, array{ts: string, open: float|string, high: float|string, low: float|string, close: float|string, volume: float|string}>
     *
     * @throws MarketDataException when the upstream API fails
     */
    public function intraday(string $symbol, ?CarbonInterface $lastTs): array;
}
