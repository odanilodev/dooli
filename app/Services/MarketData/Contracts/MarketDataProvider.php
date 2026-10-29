<?php

namespace App\Services\MarketData\Contracts;

use App\Exceptions\MarketDataException;
use Carbon\CarbonInterface;

interface MarketDataProvider
{
    /** Market code this provider serves (b3, us, crypto). */
    public function market(): string;

    /**
     * Daily candles after $lastDate (all available history when null).
     *
     * @return array<int, array{date: string, open: float|string, high: float|string, low: float|string, close: float|string, volume: float|string}>
     *
     * @throws MarketDataException when the upstream API fails
     */
    public function candles(string $symbol, ?CarbonInterface $lastDate): array;
}
