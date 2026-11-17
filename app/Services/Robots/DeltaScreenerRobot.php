<?php

namespace App\Services\Robots;

use App\Services\Robots\Contracts\RobotContract;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Screening estatístico de setup de entrada por limiar fixo (origem: DeltaStats).
 *
 * Regra (por dia): entrada = abertura * (1 - limiar) na compra, abertura * (1 + limiar)
 * na venda. O trade dispara se a mínima/máxima do dia tocar o preço de entrada; a saída
 * é sempre o fechamento. Ferramenta de triagem: sem custos, slippage ou liquidez, e cada
 * dia é independente.
 */
class DeltaScreenerRobot implements RobotContract
{
    public function rules(): array
    {
        return [
            'market' => ['required', 'in:b3,us,crypto'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'threshold' => ['required', 'numeric', 'gt:0', 'max:50'],
            'side' => ['required', 'in:buy,sell'],
            'symbols' => ['sometimes', 'array', 'max:100'],
            'symbols.*' => ['string', 'max:30'],
        ];
    }

    /** A lista de ativos agrupa todos os candles; muda no máximo a cada coleta, então vale cachear. */
    public const OPTIONS_CACHE_KEY = 'robots.delta-screener.options';

    public function options(): array
    {
        return Cache::remember(self::OPTIONS_CACHE_KEY, 300, fn () => $this->buildOptions());
    }

    private function buildOptions(): array
    {
        $markets = [];

        foreach (config('market.markets') as $code => $market) {
            $markets[$code] = ['label' => $market['label'], 'symbols' => [], 'from' => null, 'to' => null];
        }

        $assets = DB::table('market_assets as a')
            ->leftJoin('market_candles as c', 'c.market_asset_id', '=', 'a.id')
            ->groupBy('a.id', 'a.market', 'a.symbol', 'a.name')
            ->orderBy('a.symbol')
            ->selectRaw('a.market, a.symbol, a.name, MIN(c.date) as from_date, MAX(c.date) as to_date, COUNT(c.id) as candles')
            ->get();

        foreach ($assets as $asset) {
            if (! $asset->candles || ! isset($markets[$asset->market])) {
                continue;
            }

            $market = &$markets[$asset->market];
            $market['symbols'][] = ['symbol' => $asset->symbol, 'name' => $asset->name];
            $market['from'] = min($market['from'] ?? $asset->from_date, $asset->from_date);
            $market['to'] = max($market['to'] ?? $asset->to_date, $asset->to_date);
            unset($market);
        }

        return ['markets' => $markets];
    }

    public function run(array $params): array
    {
        [$entry, $triggered, $result] = $this->expressions($params['side'] === 'buy');
        $b = $this->bindings($params);

        $rows = $this->triggeredCandles($params, $triggered, $b(1))
            ->selectRaw('a.symbol, a.name, COUNT(*) as total_trades')
            ->selectRaw("SUM(CASE WHEN ({$result}) > 0 THEN 1 ELSE 0 END) as gains", $b(2))
            ->selectRaw("SUM(CASE WHEN ({$result}) <= 0 THEN 1 ELSE 0 END) as losses", $b(2))
            ->selectRaw("ROUND(SUM({$result}), 4) as net_result", $b(2))
            ->selectRaw("ROUND(AVG({$result}), 4) as avg_result", $b(2))
            ->selectRaw("ROUND(MAX({$result}), 4) as best_trade", $b(2))
            ->selectRaw("ROUND(MIN({$result}), 4) as worst_trade", $b(2))
            ->selectRaw('ROUND(AVG(c.volume), 0) as avg_volume')
            ->selectRaw("ROUND(SUM(CASE WHEN ({$result}) > 0 THEN 1 ELSE 0 END) / COUNT(*) * 100, 2) as win_rate", $b(2))
            ->groupBy('a.id', 'a.symbol', 'a.name')
            ->orderByDesc('net_result')
            ->limit(500)
            ->get();

        return [
            'rows' => $rows,
            'summary' => [
                'assets' => $rows->count(),
                'total_trades' => (int) $rows->sum('total_trades'),
                'gains' => (int) $rows->sum('gains'),
                'losses' => (int) $rows->sum('losses'),
                'net_result' => round((float) $rows->sum('net_result'), 4),
            ],
        ];
    }

    public function detail(array $params, string $symbol): array
    {
        [$entry, $triggered, $result] = $this->expressions($params['side'] === 'buy');
        $b = $this->bindings($params);

        $trades = $this->triggeredCandles($params, $triggered, $b(1))
            ->where('a.symbol', $symbol)
            ->selectRaw('c.date, c.open, c.low, c.high, c.close, c.volume')
            ->selectRaw("ROUND({$entry}, 6) as entry_price", $b(1))
            ->selectRaw("ROUND({$result}, 4) as result", $b(2))
            ->orderBy('c.date')
            ->get();

        return ['symbol' => $symbol, 'trades' => $trades];
    }

    /** Candles that touched the entry price, within market/period (and optional symbols). */
    private function triggeredCandles(array $params, string $triggered, array $triggeredBindings): Builder
    {
        $query = DB::table('market_candles as c')
            ->join('market_assets as a', 'a.id', '=', 'c.market_asset_id')
            ->where('a.market', $params['market'])
            ->whereBetween('c.date', [$params['start_date'], $params['end_date']])
            ->whereRaw($triggered, $triggeredBindings);

        if (! empty($params['symbols'])) {
            $query->whereIn('a.symbol', $params['symbols']);
        }

        return $query;
    }

    /** @return array{0: string, 1: string, 2: string} entry price, trigger condition and % result SQL */
    private function expressions(bool $isBuy): array
    {
        $entry = $isBuy ? 'c.open * (1 - ?)' : 'c.open * (1 + ?)';

        return [
            $entry,
            $isBuy ? "c.low <= {$entry}" : "c.high >= {$entry}",
            $isBuy
                ? "(c.close - ({$entry})) / ({$entry}) * 100"
                : "(({$entry}) - c.close) / ({$entry}) * 100",
        ];
    }

    /** Each occurrence of the entry expression consumes one `?` binding. */
    private function bindings(array $params): \Closure
    {
        $threshold = (float) $params['threshold'] / 100;

        return fn (int $times) => array_fill(0, $times, $threshold);
    }
}
