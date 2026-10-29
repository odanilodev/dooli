<?php

namespace App\Console\Commands;

use App\Services\MarketData\IntradayCollector;
use Illuminate\Console\Command;

class CollectIntradayMarketData extends Command
{
    protected $signature = 'market:collect-intraday {market? : b3, us ou crypto (todos se omitido)}';

    protected $description = 'Guarda no banco os candles de 15 minutos dos ativos de config/market.php.';

    public function handle(IntradayCollector $collector): int
    {
        $market = $this->argument('market');
        $markets = $market ? [$market] : $collector->markets();

        if ($market && ! in_array($market, $collector->markets(), true)) {
            $this->error('Mercado inválido. Use: '.implode(', ', $collector->markets()).'.');

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
                    ? "  {$r['symbol']}: {$r['count']} candles de 15min gravados."
                    : "  {$r['symbol']}: já atualizado.");
            });
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
