<?php

namespace App\Services\MarketData;

use App\Exceptions\MarketDataException;
use App\Models\MarketAsset;
use App\Models\MarketCandle;
use App\Services\MarketData\Contracts\MarketDataProvider;
use App\Services\MarketData\Providers\BinanceProvider;
use App\Services\MarketData\Providers\BrapiProvider;
use App\Services\MarketData\Providers\YahooFinanceB3Provider;
use App\Services\MarketData\Providers\YahooFinanceProvider;
use Carbon\Carbon;

/**
 * Baixa candles diários de cada mercado e grava de forma idempotente
 * (upsert por ativo+data). Um provedor por fonte; os ativos vêm de config/market.php.
 */
class MarketDataCollector
{
    /** @var array<string, class-string<MarketDataProvider>> */
    protected array $providers = [
        'b3' => BrapiProvider::class,
        'us' => YahooFinanceProvider::class,
        'crypto' => BinanceProvider::class,
    ];

    /** B3 usa a Brapi quando há token; sem ele cai no Yahoo (sufixo .SA). */
    public function providerFor(string $market): string
    {
        if ($market === 'b3' && ! config('services.brapi.token')) {
            return YahooFinanceB3Provider::class;
        }

        return $this->providers[$market];
    }

    /** @return array<int, string> */
    public function markets(): array
    {
        return array_keys($this->providers);
    }

    /**
     * @param  callable(array{symbol: string, status: string, count: int, message: ?string}): void|null  $onResult
     * @return array<int, array{symbol: string, status: string, count: int, message: ?string}>
     */
    public function collect(string $market, ?callable $onResult = null): array
    {
        $provider = app($this->providerFor($market));
        $results = [];

        foreach (MarketAsset::symbolsFor($market) as $symbol) {
            $result = $this->collectSymbol($provider, $market, $symbol);
            $results[] = $result;

            if ($onResult) {
                $onResult($result);
            }

            usleep(200_000); // não estressar as APIs públicas
        }

        return $results;
    }

    public function collectSymbol(MarketDataProvider $provider, string $market, string $symbol): array
    {
        $asset = MarketAsset::query()->firstOrCreate(['market' => $market, 'symbol' => $symbol]);
        $last = $asset->candles()->max('date');

        try {
            $candles = $provider->candles($symbol, $last ? Carbon::parse($last) : null);
        } catch (MarketDataException $e) {
            return ['symbol' => $symbol, 'status' => 'error', 'count' => 0, 'message' => $e->getMessage()];
        }

        if ($candles === []) {
            return ['symbol' => $symbol, 'status' => 'up_to_date', 'count' => 0, 'message' => null];
        }

        $now = now();

        MarketCandle::query()->upsert(
            array_map(fn (array $c) => $c + [
                'market_asset_id' => $asset->id,
                'created_at' => $now,
                'updated_at' => $now,
            ], $candles),
            uniqueBy: ['market_asset_id', 'date'],
            update: ['open', 'high', 'low', 'close', 'volume', 'updated_at'],
        );

        return ['symbol' => $symbol, 'status' => 'ok', 'count' => count($candles), 'message' => null];
    }
}
