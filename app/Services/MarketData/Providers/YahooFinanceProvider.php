<?php

namespace App\Services\MarketData\Providers;

use App\Exceptions\MarketDataException;
use App\Services\MarketData\Contracts\IntradayProvider;
use App\Services\MarketData\Contracts\MarketDataProvider;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;

/** Mercado americano via endpoint público (não oficial) do Yahoo Finance. */
class YahooFinanceProvider implements IntradayProvider, MarketDataProvider
{
    public function market(): string
    {
        return 'us';
    }

    protected function ticker(string $symbol): string
    {
        return $symbol;
    }

    public function candles(string $symbol, ?CarbonInterface $lastDate): array
    {
        $from = $lastDate
            ? Carbon::instance($lastDate)->addDay()->startOfDay()
            : now()->subMonths((int) config('market.history_months'))->startOfDay();

        if ($from->isAfter(now())) {
            return [];
        }

        $response = Http::withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; DeltaClass/1.0)'])
            ->get("https://query1.finance.yahoo.com/v8/finance/chart/{$this->ticker($symbol)}", [
                'period1' => $from->timestamp,
                'period2' => now()->timestamp,
                'interval' => '1d',
            ]);

        if ($response->failed()) {
            throw MarketDataException::upstream('Yahoo Finance', $symbol, $response->status());
        }

        $result = data_get($response->json(), 'chart.result.0');
        $quote = data_get($result, 'indicators.quote.0', []);
        $candles = [];

        foreach (data_get($result, 'timestamp', []) as $i => $timestamp) {
            $row = [
                'open' => $quote['open'][$i] ?? null,
                'high' => $quote['high'][$i] ?? null,
                'low' => $quote['low'][$i] ?? null,
                'close' => $quote['close'][$i] ?? null,
                'volume' => $quote['volume'][$i] ?? null,
            ];

            // O Yahoo devolve nulos em dias sem pregão completo.
            if (in_array(null, $row, true) || ! $row['volume']) {
                continue;
            }

            $candles[] = ['date' => Carbon::createFromTimestamp($timestamp)->toDateString()] + $row;
        }

        return $candles;
    }

    public function intraday(string $symbol, ?CarbonInterface $lastTs): array
    {
        // O Yahoo só guarda ~60 dias de candles de 15min: o histórico daqui pra frente é o nosso banco.
        $from = $lastTs
            ? Carbon::instance($lastTs)
            : now()->subDays((int) config('market.intraday_backfill_days'));

        if ($from->isAfter(now())) {
            return [];
        }

        $response = Http::withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; DeltaClass/1.0)'])
            ->get("https://query1.finance.yahoo.com/v8/finance/chart/{$this->ticker($symbol)}", [
                'period1' => $from->timestamp,
                'period2' => now()->timestamp,
                'interval' => '15m',
            ]);

        if ($response->failed()) {
            throw MarketDataException::upstream('Yahoo Finance', $symbol, $response->status());
        }

        $result = data_get($response->json(), 'chart.result.0');
        $quote = data_get($result, 'indicators.quote.0', []);
        $candles = [];

        foreach (data_get($result, 'timestamp', []) as $i => $timestamp) {
            $row = [
                'open' => $quote['open'][$i] ?? null,
                'high' => $quote['high'][$i] ?? null,
                'low' => $quote['low'][$i] ?? null,
                'close' => $quote['close'][$i] ?? null,
            ];

            // Ignora a cotação "ao vivo" do Yahoo (fora da grade de 15min); o candle fecha na próxima rodada.
            if (in_array(null, $row, true) || $timestamp % 900 !== 0) {
                continue;
            }

            $candles[] = ['ts' => Carbon::createFromTimestampUTC($timestamp)->toDateTimeString()]
                + $row + ['volume' => $quote['volume'][$i] ?? 0];
        }

        return $candles;
    }
}
