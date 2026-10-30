<?php

namespace App\Services\MarketData;

use App\Exceptions\MarketDataException;
use App\Models\MarketAsset;
use App\Models\MarketIntradayCandle;
use App\Services\MarketData\Contracts\IntradayProvider;
use App\Services\MarketData\Providers\BinanceProvider;
use App\Services\MarketData\Providers\YahooFinanceB3Provider;
use App\Services\MarketData\Providers\YahooFinanceProvider;
use Carbon\Carbon;

/**
 * Guarda candles de 15 minutos no banco, de forma incremental e idempotente
 * (cada execução continua do último candle salvo). Pode rodar várias vezes ao dia.
 */
class IntradayCollector
{
    /** @var array<string, class-string<IntradayProvider>> */
    protected array $providers = [
        'b3' => YahooFinanceB3Provider::class,
        'us' => YahooFinanceProvider::class,
        'crypto' => BinanceProvider::class,
    ];

    public function provider(string $market): IntradayProvider
    {
        return app($this->providers[$market]);
    }

    /** @return array<int, string> */
    public function markets(): array
    {
        return array_keys($this->providers);
    }

    /**
     * @param  callable(array{symbol: string, status: string, count: int, message: ?string}): void|null  $onResult
     */
    public function collect(string $market, ?callable $onResult = null): array
    {
        $provider = app($this->providers[$market]);
        $results = [];

        foreach (MarketAsset::symbolsFor($market) as $symbol) {
            $result = $this->collectSymbol($provider, $market, $symbol);
            $results[] = $result;

            if ($onResult) {
                $onResult($result);
            }

            usleep(200_000);
        }

        return $results;
    }

    public function collectSymbol(IntradayProvider $provider, string $market, string $symbol): array
    {
        $asset = MarketAsset::query()->firstOrCreate(['market' => $market, 'symbol' => $symbol]);
        $last = MarketIntradayCandle::query()->where('market_asset_id', $asset->id)->max('ts');

        try {
            $candles = $provider->intraday($symbol, $last ? Carbon::parse($last, 'UTC') : null);
        } catch (MarketDataException $e) {
            return ['symbol' => $symbol, 'status' => 'error', 'count' => 0, 'message' => $e->getMessage()];
        }

        if ($candles === []) {
            return ['symbol' => $symbol, 'status' => 'up_to_date', 'count' => 0, 'message' => null];
        }

        $now = now();

        foreach (array_chunk($candles, 500) as $chunk) {
            MarketIntradayCandle::query()->upsert(
                array_map(fn (array $c) => $c + [
                    'market_asset_id' => $asset->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $chunk),
                uniqueBy: ['market_asset_id', 'ts'],
                update: ['open', 'high', 'low', 'close', 'volume', 'updated_at'],
            );
        }

        return ['symbol' => $symbol, 'status' => 'ok', 'count' => count($candles), 'message' => null];
    }
}
