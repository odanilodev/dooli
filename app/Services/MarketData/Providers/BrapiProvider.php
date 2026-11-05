<?php

namespace App\Services\MarketData\Providers;

use App\Exceptions\MarketDataException;
use App\Services\MarketData\Contracts\MarketDataProvider;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;

/** B3 via brapi.dev. Sem token só PETR4, VALE3, ITUB4 e MGLU3 respondem. */
class BrapiProvider implements MarketDataProvider
{
    public function market(): string
    {
        return 'b3';
    }

    public function candles(string $symbol, ?CarbonInterface $lastDate): array
    {
        // O range da brapi é uma janela grosseira (não uma data exata): usa a menor
        // janela que cobre o que falta desde a última coleta.
        $range = $lastDate && $lastDate->gt(now()->subMonth()) ? '1mo' : '2y';

        $response = Http::get("https://brapi.dev/api/quote/{$symbol}", array_filter([
            'range' => $range,
            'interval' => '1d',
            'token' => config('services.brapi.token'),
        ]));

        if ($response->failed()) {
            throw MarketDataException::upstream('brapi.dev', $symbol, $response->status());
        }

        $candles = [];

        foreach (data_get($response->json(), 'results.0.historicalDataPrice', []) as $price) {
            if (! isset($price['open'], $price['high'], $price['low'], $price['close'], $price['volume'])) {
                continue;
            }

            $date = Carbon::createFromTimestamp($price['date'])->toDateString();

            if ($lastDate && $date <= $lastDate->toDateString()) {
                continue;
            }

            $candles[] = [
                'date' => $date,
                'open' => $price['open'],
                'high' => $price['high'],
                'low' => $price['low'],
                'close' => $price['close'],
                'volume' => $price['volume'],
            ];
        }

        return $candles;
    }
}
