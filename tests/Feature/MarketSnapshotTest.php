<?php

namespace Tests\Feature;

use App\Models\MarketAsset;
use App\Models\MarketCandle;
use App\Services\MarketData\SnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarketSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private string $file;

    protected function setUp(): void
    {
        parent::setUp();
        $this->file = tempnam(sys_get_temp_dir(), 'snap').'.csv.gz';
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        parent::tearDown();
    }

    private function seedCandles(): void
    {
        $asset = MarketAsset::query()->create(['market' => 'b3', 'symbol' => 'PETR4', 'name' => 'Petrobras, PN']);
        $crypto = MarketAsset::query()->create(['market' => 'crypto', 'symbol' => 'BTCUSDT']);

        foreach ([[$asset, '2026-10-01', 30.5], [$asset, '2026-10-02', 31.25], [$crypto, '2026-10-01', 68000.1]] as [$a, $date, $close]) {
            // Insere a data como texto puro (como a coleta faz); o model a gravaria com hora no SQLite.
            DB::table('market_candles')->insert([
                'market_asset_id' => $a->id, 'date' => $date, 'open' => $close - 1, 'high' => $close + 1,
                'low' => $close - 2, 'close' => $close, 'volume' => 1234.5,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function test_export_then_import_restores_assets_and_candles(): void
    {
        $this->seedCandles();
        $service = app(SnapshotService::class);

        $this->assertSame(3, $service->export($this->file));

        DB::table('market_candles')->delete();
        DB::table('market_assets')->delete();

        $result = $service->import($this->file);

        $this->assertSame(['assets' => 2, 'candles' => 3], $result);
        $this->assertSame(2, MarketAsset::query()->count());
        $this->assertSame('Petrobras, PN', MarketAsset::query()->where('symbol', 'PETR4')->value('name'));
        $this->assertEqualsWithDelta(31.25, (float) DB::table('market_candles')->where('date', '2026-10-02')->value('close'), 0.0001);
    }

    public function test_import_is_idempotent_and_never_overwrites_collected_candles(): void
    {
        $this->seedCandles();
        $service = app(SnapshotService::class);
        $service->export($this->file);

        // Candle já coletado mais recentemente (valor diferente do snapshot) deve ser preservado.
        DB::table('market_candles')->where('date', '2026-10-02')->update(['close' => 99]);

        $result = $service->import($this->file);

        $this->assertSame(['assets' => 0, 'candles' => 0], $result);
        $this->assertSame(3, MarketCandle::query()->count());
        $this->assertEqualsWithDelta(99, (float) DB::table('market_candles')->where('date', '2026-10-02')->value('close'), 0.0001);
    }

    public function test_invalid_file_is_rejected(): void
    {
        file_put_contents($this->file, gzencode("a,b,c\n1,2,3\n"));

        $this->expectExceptionMessage('Formato de snapshot inválido.');

        app(SnapshotService::class)->import($this->file);
    }
}
