<?php

namespace Tests\Feature;

use App\Models\MarketAsset;
use App\Models\MarketCandle;
use App\Models\Robot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class StudentRobotTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();

        Robot::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'slug' => 'delta-screener',
            'name' => 'DeltaStats',
        ]);

        $asset = MarketAsset::query()->create(['market' => 'b3', 'symbol' => 'PETR4']);

        // Dia 1: compra a 2% dispara (entrada 29.40, mínima 28.50), fecha em 30.50 => ganho.
        // Dia 2: mínima 29.90 não alcança a entrada 29.40 => não dispara.
        // Dia 3: entrada 30.38, mínima 30.10, fecha em 30.00 => perda.
        foreach ([
            ['2026-10-01', 30, 31, 28.5, 30.5],
            ['2026-10-02', 30, 30.4, 29.9, 30.2],
            ['2026-10-05', 31, 31.2, 30.1, 30.0],
        ] as [$date, $open, $high, $low, $close]) {
            MarketCandle::query()->create([
                'market_asset_id' => $asset->id,
                'date' => $date,
                'open' => $open,
                'high' => $high,
                'low' => $low,
                'close' => $close,
                'volume' => 1000,
            ]);
        }
    }

    private function params(array $override = []): array
    {
        return array_merge([
            'market' => 'b3',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'threshold' => 2,
            'side' => 'buy',
        ], $override);
    }

    public function test_student_lists_robots(): void
    {
        $this->withToken($this->apiLogin($this->student))
            ->getJson('/api/v1/me/robots')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'delta-screener');
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/me/robots')->assertUnauthorized();
    }

    public function test_options_expose_assets_and_data_range(): void
    {
        $b3 = $this->withToken($this->apiLogin($this->student))
            ->getJson('/api/v1/me/robots/delta-screener')
            ->assertOk()
            ->assertJsonPath('data.options.markets.b3.symbols.0.symbol', 'PETR4')
            ->json('data.options.markets.b3');

        // O SQLite dos testes devolve datetime; no MySQL a coluna `date` vem sem hora.
        $this->assertSame('2026-10-01', substr($b3['from'], 0, 10));
        $this->assertSame('2026-10-05', substr($b3['to'], 0, 10));
    }

    public function test_run_aggregates_triggered_trades_per_asset(): void
    {
        $this->withToken($this->apiLogin($this->student))
            ->postJson('/api/v1/me/robots/delta-screener/run', $this->params())
            ->assertOk()
            ->assertJsonPath('data.rows.0.symbol', 'PETR4')
            ->assertJsonPath('data.rows.0.total_trades', 2)
            ->assertJsonPath('data.rows.0.gains', 1)
            ->assertJsonPath('data.rows.0.losses', 1)
            ->assertJsonPath('data.summary.total_trades', 2);
    }

    public function test_detail_lists_individual_trades(): void
    {
        $response = $this->withToken($this->apiLogin($this->student))
            ->postJson('/api/v1/me/robots/delta-screener/detail', $this->params(['symbol' => 'PETR4']))
            ->assertOk()
            ->assertJsonCount(2, 'data.trades');

        $this->assertSame('2026-10-01', substr($response->json('data.trades.0.date'), 0, 10));
        $this->assertEqualsWithDelta(3.7415, (float) $response->json('data.trades.0.result'), 0.0001);
    }

    public function test_run_validates_parameters(): void
    {
        $this->withToken($this->apiLogin($this->student))
            ->postJson('/api/v1/me/robots/delta-screener/run', ['market' => 'xx'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['market', 'start_date', 'end_date', 'threshold', 'side'], 'errors');
    }

    public function test_unknown_robot_returns_not_found(): void
    {
        $this->withToken($this->apiLogin($this->student))
            ->getJson('/api/v1/me/robots/nao-existe')
            ->assertNotFound();
    }

    public function test_inactive_robot_is_hidden(): void
    {
        Robot::withoutGlobalScopes()->where('slug', 'delta-screener')->update(['status' => 'inactive']);

        $this->withToken($this->apiLogin($this->student))
            ->getJson('/api/v1/me/robots')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
