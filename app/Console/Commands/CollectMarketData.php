<?php

namespace App\Console\Commands;

use App\Services\MarketData\MarketDataCollector;
use Illuminate\Console\Command;

class CollectMarketData extends Command
{
    protected $signature = 'market:collect {market? : b3, us ou crypto (todos se omitido)}';

    protected $description = 'Coleta candles diários dos ativos de config/market.php para os robôs.';

    public function handle(MarketDataCollector $collector): int
    {
        $market = $this->argument('market');
        $markets = $market ? [$market] : $collector->markets();

        if ($market && ! in_array($market, $collector->markets(), true)) {
            $this->error("Mercado inválido. Use: ".implode(', ', $collector->markets()).'.');

            return self::INVALID;
        }

        $failed = false;

        foreach ($markets as $m) {
            $this->components->info("Mercado: {$m}");

            $collector->collect($m, function (array $r) use (&$failed) {
                if ($r['status'] === 'error') {
                    $failed = true;
                    $this->components->error($r['message']);

                    return;
                }

                $this->line($r['status'] === 'ok'
                    ? "  {$r['symbol']}: {$r['count']} candles gravados."
                    : "  {$r['symbol']}: já atualizado.");
            });
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
