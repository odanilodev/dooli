<?php

namespace App\Services\MarketData\Providers;

use App\Exceptions\MarketDataException;
use App\Services\MarketData\Contracts\IntradayProvider;
use App\Services\MarketData\Contracts\MarketDataProvider;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;

/** Cripto via API pública da Binance (pares em USDT, sem chave). */
class BinanceProvider implements IntradayProvider, MarketDataProvider
{
    public function market(): string
    {
        return 'crypto';
    }

    public function candles(string $symbol, ?CarbonInterface $lastDate): array
    {
        $from = $lastDate
            ? Carbon::instance($lastDate)->addDay()->startOfDay()
            : now()->subMonths((int) config('market.history_months'))->startOfDay();

        if ($from->isAfter(now())) {
            return [];
        }

        $response = Http::get('https://api.binance.com/api/v3/klines', [
            'symbol' => $symbol,
            'interval' => '1d',
            'startTime' => $from->getTimestampMs(),
            'limit' => 1000,
        ]);

        if ($response->failed()) {
            throw MarketDataException::upstream('Binance', $symbol, $response->status());
        }

        return collect($response->json())
            ->map(fn (array $k) => [
                'date' => Carbon::createFromTimestampMs($k[0])->toDateString(),
                'open' => $k[1],
                'high' => $k[2],
                'low' => $k[3],
                'close' => $k[4],
                'volume' => $k[5],
            ])
            ->all();
    }

    public function intraday(string $symbol, ?CarbonInterface $lastTs): array
    {
        $from = $lastTs
            ? Carbon::instance($lastTs)
            : now()->subDays((int) config('market.intraday_backfill_days'));
        $candles = [];

        // 1000 candles por chamada (~10 dias de 15min): pagina até alcançar o presente.
        while ($from->isBefore(now())) {
            $response = Http::get('https://api.binance.com/api/v3/klines', [
                'symbol' => $symbol,
                'interval' => '15m',
                'startTime' => $from->getTimestampMs(),
                'limit' => 1000,
            ]);

            if ($response->failed()) {
                throw MarketDataException::upstream('Binance', $symbol, $response->status());
            }

            $page = $response->json();

            if ($page === []) {
                break;
            }

            foreach ($page as $k) {
                $candles[] = [
                    'ts' => Carbon::createFromTimestampMsUTC($k[0])->toDateTimeString(),
                    'open' => $k[1],
                    'high' => $k[2],
                    'low' => $k[3],
                    'close' => $k[4],
                    'volume' => $k[5],
                ];
            }

            $from = Carbon::createFromTimestampMsUTC(end($page)[0])->addMinutes(15);
        }

        return $candles;
    }
}
