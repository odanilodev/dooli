<?php

namespace Tests\Feature;

use App\Models\MarketAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DeleteB3CandlesOf20261026Test extends TestCase
{
    use RefreshDatabase;

    private function candle(MarketAsset $asset, string $date): void
    {
        DB::table('market_candles')->insert([
            'market_asset_id' => $asset->id, 'date' => $date,
            'open' => 10, 'high' => 11, 'low' => 9, 'close' => 10, 'volume' => 100,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function intraday(MarketAsset $asset, string $ts): void
    {
        DB::table('market_intraday_candles')->insert([
            'market_asset_id' => $asset->id, 'ts' => $ts,
            'open' => 10, 'high' => 11, 'low' => 9, 'close' => 10, 'volume' => 100,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_deletes_only_b3_data_of_the_day(): void
    {
        $b3 = MarketAsset::query()->create(['market' => 'b3', 'symbol' => 'PETR4']);
        $us = MarketAsset::query()->create(['market' => 'us', 'symbol' => 'AAPL']);

        foreach ([$b3, $us] as $asset) {
            $this->candle($asset, '2026-10-23');
            $this->candle($asset, '2026-10-26');
            $this->candle($asset, '2026-10-27');
            $this->intraday($asset, '2026-10-26 13:00:00');
            $this->intraday($asset, '2026-10-27 13:00:00');
        }

        (require database_path('migrations/2026_11_13_000100_delete_b3_candles_of_2026_10_26.php'))->up();

        $dates = fn (MarketAsset $a) => DB::table('market_candles')->where('market_asset_id', $a->id)->orderBy('date')
            ->pluck('date')->map(fn ($d) => substr((string) $d, 0, 10))->all();

        $this->assertSame(['2026-10-23', '2026-10-27'], $dates($b3));
        $this->assertSame(['2026-10-23', '2026-10-26', '2026-10-27'], $dates($us));

        $this->assertSame(1, DB::table('market_intraday_candles')->where('market_asset_id', $b3->id)->count());
        $this->assertSame(2, DB::table('market_intraday_candles')->where('market_asset_id', $us->id)->count());
        $this->assertSame(2, DB::table('market_assets')->count());
    }
}
