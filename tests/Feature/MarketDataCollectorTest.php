<?php

namespace Tests\Feature;

use App\Models\MarketAsset;
use App\Services\MarketData\MarketDataCollector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MarketDataCollectorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['market.markets.crypto.symbols' => ['BTCUSDT']]);
        config(['market.markets.b3.symbols' => ['PETR4', 'VALE3']]);
        config(['services.brapi.token' => 'test-token']);
    }

    public function test_b3_falls_back_to_yahoo_when_there_is_no_brapi_token(): void
    {
        config(['services.brapi.token' => null]);
        config(['market.markets.b3.symbols' => ['PETR4']]);

        Http::fake(['query1.finance.yahoo.com/v8/finance/chart/PETR4.SA*' => Http::response(['chart' => ['result' => [[
            'timestamp' => [strtotime('2026-10-01 13:00:00 UTC'), strtotime('2026-10-02 13:00:00 UTC')],
            'indicators' => ['quote' => [[
                'open' => [10, 11], 'high' => [12, 12], 'low' => [9, 10], 'close' => [11, 12], 'volume' => [100, 200],
            ]]],
        ]]]])]);

        $results = app(MarketDataCollector::class)->collect('b3');

        $this->assertSame('ok', $results[0]['status']);
        $this->assertSame(2, MarketAsset::query()->where('symbol', 'PETR4')->firstOrFail()->candles()->count());
    }

    private function klines(): array
    {
        // [openTime, open, high, low, close, volume]
        return [
            [strtotime('2026-10-01 00:00:00 UTC') * 1000, '100', '110', '95', '105', '10'],
            [strtotime('2026-10-02 00:00:00 UTC') * 1000, '105', '112', '100', '108', '12'],
        ];
    }

    public function test_collects_and_stores_candles_for_a_market(): void
    {
        Http::fake(['api.binance.com/*' => Http::response($this->klines())]);

        $results = app(MarketDataCollector::class)->collect('crypto');

        $this->assertSame('ok', $results[0]['status']);
        $this->assertSame(2, $results[0]['count']);

        $asset = MarketAsset::query()->where('symbol', 'BTCUSDT')->firstOrFail();
        $this->assertSame(2, $asset->candles()->count());
    }

    public function test_collection_is_idempotent(): void
    {
        Http::fake(['api.binance.com/*' => Http::response($this->klines())]);

        $collector = app(MarketDataCollector::class);
        $collector->collect('crypto');
        $collector->collect('crypto');

        $this->assertSame(2, MarketAsset::query()->firstOrFail()->candles()->count());
    }

    public function test_one_failing_symbol_does_not_stop_the_others(): void
    {
        Http::fake([
            'brapi.dev/api/quote/PETR4*' => Http::response([], 401),
            'brapi.dev/api/quote/VALE3*' => Http::response(['results' => [['historicalDataPrice' => [
                ['date' => strtotime('2026-10-01'), 'open' => 60, 'high' => 62, 'low' => 59, 'close' => 61, 'volume' => 500],
            ]]]]),
        ]);

        $results = collect(app(MarketDataCollector::class)->collect('b3'))->keyBy('symbol');

        $this->assertSame('error', $results['PETR4']['status']);
        $this->assertStringContainsString('401', $results['PETR4']['message']);
        $this->assertSame('ok', $results['VALE3']['status']);
    }

    public function test_command_rejects_unknown_market(): void
    {
        $this->artisan('market:collect', ['market' => 'forex'])->assertExitCode(2);
    }
}
