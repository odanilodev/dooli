<?php

namespace Tests\Feature;

use App\Enums\GeneralStatus;
use App\Models\MarketAsset;
use App\Models\MarketCandle;
use App\Models\Robot;
use App\Models\RobotUserSetting;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class StudentRobotTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    private Robot $robot;

    /** Turma que libera a ferramenta, com o aluno matriculado (acesso ativo). */
    private function grantAccess($user, ?string $expiredAt = null): Team
    {
        $team = Team::factory()->create(['company_id' => $this->company->id, 'status' => GeneralStatus::Active->value]);
        $team->robots()->attach($this->robot->id, ['company_id' => $this->company->id]);
        $team->users()->attach($user->id, ['company_id' => $this->company->id, 'status' => GeneralStatus::Active->value, 'expired_at' => $expiredAt]);

        return $team;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();

        $robot = Robot::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'slug' => 'delta-screener',
            'name' => 'DeltaStats',
        ]);

        $this->robot = $robot;
        $this->grantAccess($this->student);

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
            ->assertJsonPath('data.rows.0.avg_volume', 1000)
            // Volume financeiro = volume * fechamento: (1000*30.5 + 1000*30.0) / 2.
            ->assertJsonPath('data.rows.0.avg_financial_volume', 30250)
            ->assertJsonPath('data.summary.total_trades', 2);
    }

    public function test_native_quote_volume_takes_priority_over_the_estimate(): void
    {
        $asset = MarketAsset::query()->create(['market' => 'crypto', 'symbol' => 'BTCUSDT']);

        // Compra a 2% dispara (entrada 98, mínima 95). Estimativa seria 2 * 100 = 200;
        // o volume financeiro nativo da fonte (180) é o que vale.
        MarketCandle::query()->create([
            'market_asset_id' => $asset->id, 'date' => '2026-10-01',
            'open' => 100, 'high' => 101, 'low' => 95, 'close' => 100,
            'volume' => 2, 'quote_volume' => 180,
        ]);

        $token = $this->apiLogin($this->student);

        $this->withToken($token)
            ->postJson('/api/v1/me/robots/delta-screener/run', $this->params(['market' => 'crypto']))
            ->assertOk()
            ->assertJsonPath('data.rows.0.avg_financial_volume', 180);

        $this->withToken($token)
            ->postJson('/api/v1/me/robots/delta-screener/detail', $this->params(['market' => 'crypto', 'symbol' => 'BTCUSDT']))
            ->assertOk()
            ->assertJsonPath('data.trades.0.financial_volume', 180);
    }

    public function test_detail_lists_individual_trades(): void
    {
        $response = $this->withToken($this->apiLogin($this->student))
            ->postJson('/api/v1/me/robots/delta-screener/detail', $this->params(['symbol' => 'PETR4']))
            ->assertOk()
            ->assertJsonCount(2, 'data.trades');

        $this->assertSame('2026-10-01', substr($response->json('data.trades.0.date'), 0, 10));
        $this->assertEqualsWithDelta(3.7415, (float) $response->json('data.trades.0.result'), 0.0001);
        $this->assertEqualsWithDelta(30500, (float) $response->json('data.trades.0.financial_volume'), 0.01);
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

    public function test_student_without_access_does_not_see_the_robot(): void
    {
        $other = User::factory()->create([
            'company_id' => $this->company->id,
            'user_type_id' => $this->student->user_type_id,
        ]);
        $token = $this->apiLogin($other);

        $this->withToken($token)->getJson('/api/v1/me/robots')->assertOk()->assertJsonCount(0, 'data');
        $this->withToken($token)->getJson('/api/v1/me/robots/delta-screener')->assertNotFound();
        $this->withToken($token)->postJson('/api/v1/me/robots/delta-screener/run', $this->params())->assertNotFound();
    }

    public function test_expired_team_membership_removes_access(): void
    {
        DB::table('team_users')->where('user_id', $this->student->id)
            ->update(['expired_at' => now()->subDay()]);

        $this->withToken($this->apiLogin($this->student))
            ->getJson('/api/v1/me/robots')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_inactive_membership_removes_access(): void
    {
        DB::table('team_users')->where('user_id', $this->student->id)
            ->update(['status' => GeneralStatus::Inactive->value]);

        $this->withToken($this->apiLogin($this->student))
            ->getJson('/api/v1/me/robots')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_asset_rules_come_with_the_default(): void
    {
        $response = $this->withToken($this->apiLogin($this->student))
            ->getJson('/api/v1/me/robots/delta-screener')
            ->assertOk()
            ->assertJsonPath('data.settings.asset_rules.min_trades', ['enabled' => true, 'value' => 10])
            ->assertJsonPath('data.settings.asset_rules.min_win_rate', ['enabled' => true, 'value' => 70])
            ->assertJsonPath('data.settings.asset_rules.max_worst_loss', ['enabled' => true, 'value' => 4]);

        $this->assertEqualsWithDelta(0.2, $response->json('data.settings.asset_rules.min_avg_result.value'), 0.0001);
        $this->assertSame($response->json('data.settings'), $response->json('data.default_settings'));
    }

    private function customRules(): array
    {
        return ['asset_rules' => [
            'min_trades' => ['enabled' => true, 'value' => 20],
            'min_win_rate' => ['enabled' => true, 'value' => 65],
            'min_avg_result' => ['enabled' => false, 'value' => 0.5],
            'max_worst_loss' => ['enabled' => true, 'value' => 3],
        ]];
    }

    public function test_user_saves_own_asset_rules(): void
    {
        $token = $this->apiLogin($this->student);

        $this->withToken($token)
            ->putJson('/api/v1/me/robots/delta-screener/settings', $this->customRules())
            ->assertOk()
            ->assertJsonPath('data.settings.asset_rules.min_trades.value', 20);

        $this->withToken($token)
            ->getJson('/api/v1/me/robots/delta-screener')
            ->assertOk()
            ->assertJsonPath('data.settings.asset_rules.min_trades.value', 20)
            ->assertJsonPath('data.settings.asset_rules.min_avg_result.enabled', false)
            ->assertJsonPath('data.settings.asset_rules.max_worst_loss.value', 3)
            // O padrão continua disponível para o botão "Restaurar padrão".
            ->assertJsonPath('data.default_settings.asset_rules.min_trades.value', 10);

        $this->assertDatabaseHas('robot_user_settings', [
            'robot_id' => $this->robot->id,
            'user_id' => $this->student->id,
            'company_id' => $this->company->id,
        ]);

        // Salvar de novo atualiza a mesma linha, não cria outra.
        $this->withToken($token)->putJson('/api/v1/me/robots/delta-screener/settings', $this->customRules())->assertOk();
        $this->assertSame(1, DB::table('robot_user_settings')->count());
    }

    public function test_saved_rules_are_per_user(): void
    {
        $this->withToken($this->apiLogin($this->student))
            ->putJson('/api/v1/me/robots/delta-screener/settings', $this->customRules())
            ->assertOk();

        $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/me/robots/delta-screener')
            ->assertOk()
            ->assertJsonPath('data.settings.asset_rules.min_trades.value', 10);
    }

    public function test_reset_restores_the_default_rules(): void
    {
        $token = $this->apiLogin($this->student);

        $this->withToken($token)->putJson('/api/v1/me/robots/delta-screener/settings', $this->customRules())->assertOk();

        $this->withToken($token)
            ->deleteJson('/api/v1/me/robots/delta-screener/settings')
            ->assertOk()
            ->assertJsonPath('data.settings.asset_rules.min_trades.value', 10);

        $this->assertSame(0, DB::table('robot_user_settings')->count());

        $this->withToken($token)
            ->getJson('/api/v1/me/robots/delta-screener')
            ->assertJsonPath('data.settings.asset_rules.min_win_rate.value', 70);
    }

    public function test_asset_rules_are_validated(): void
    {
        $rules = $this->customRules();
        $rules['asset_rules']['min_win_rate']['value'] = 150;
        $rules['asset_rules']['max_worst_loss']['value'] = -2;
        unset($rules['asset_rules']['min_trades']);
        $rules['asset_rules']['hack'] = ['enabled' => true, 'value' => 1];

        $this->withToken($this->apiLogin($this->student))
            ->putJson('/api/v1/me/robots/delta-screener/settings', $rules)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'asset_rules',
                'asset_rules.min_trades',
                'asset_rules.min_win_rate.value',
                'asset_rules.max_worst_loss.value',
            ], 'errors');

        $this->assertSame(0, DB::table('robot_user_settings')->count());
    }

    public function test_disabled_rule_can_be_saved_with_empty_value(): void
    {
        $rules = $this->customRules();
        $rules['asset_rules']['min_avg_result'] = ['enabled' => false, 'value' => ''];

        $response = $this->withToken($this->apiLogin($this->student))
            ->putJson('/api/v1/me/robots/delta-screener/settings', $rules)
            ->assertOk()
            ->assertJsonPath('data.settings.asset_rules.min_avg_result.enabled', false);

        // Sem valor, guarda o padrão para a regra voltar preenchida se for religada.
        $this->assertEqualsWithDelta(0.2, $response->json('data.settings.asset_rules.min_avg_result.value'), 0.0001);
    }

    public function test_enabled_rule_requires_a_value(): void
    {
        $rules = $this->customRules();
        $rules['asset_rules']['min_trades']['value'] = '';

        $this->withToken($this->apiLogin($this->student))
            ->putJson('/api/v1/me/robots/delta-screener/settings', $rules)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['asset_rules.min_trades.value'], 'errors');
    }

    public function test_saved_keys_that_no_longer_exist_are_ignored(): void
    {
        RobotUserSetting::query()->create([
            'company_id' => $this->company->id,
            'robot_id' => $this->robot->id,
            'user_id' => $this->student->id,
            'settings' => ['asset_rules' => ['regra_antiga' => ['enabled' => true, 'value' => 1], 'min_trades' => ['enabled' => true, 'value' => 15]]],
        ]);

        $this->withToken($this->apiLogin($this->student))
            ->getJson('/api/v1/me/robots/delta-screener')
            ->assertOk()
            ->assertJsonPath('data.settings.asset_rules.min_trades.value', 15)
            ->assertJsonMissingPath('data.settings.asset_rules.regra_antiga');
    }

    public function test_student_without_access_cannot_save_rules(): void
    {
        $other = User::factory()->create([
            'company_id' => $this->company->id,
            'user_type_id' => $this->student->user_type_id,
        ]);

        $this->withToken($this->apiLogin($other))
            ->putJson('/api/v1/me/robots/delta-screener/settings', $this->customRules())
            ->assertNotFound();
    }

    public function test_admin_sees_every_robot_without_a_team(): void
    {
        $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/me/robots')->assertOk()->assertJsonPath('data.0.slug', 'delta-screener');
    }
}
