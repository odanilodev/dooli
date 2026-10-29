<?php

namespace App\Console\Commands;

use App\Services\MarketData\SnapshotService;
use Illuminate\Console\Command;

class MarketSnapshot extends Command
{
    protected $signature = 'market:snapshot {action : export ou import} {path? : arquivo .csv.gz (padrão: database/data/market_candles.csv.gz)}';

    protected $description = 'Exporta ou importa os candles diários em um arquivo comprimido (leva a base de um ambiente a outro).';

    public function handle(SnapshotService $snapshot): int
    {
        $path = $this->argument('path') ?: base_path(SnapshotService::DEFAULT_PATH);

        if ($this->argument('action') === 'export') {
            @mkdir(dirname($path), 0777, true);
            $this->info("{$snapshot->export($path)} candles gravados em {$path}");

            return self::SUCCESS;
        }

        if ($this->argument('action') === 'import') {
            $result = $snapshot->import($path);
            $this->info("{$result['assets']} ativos e {$result['candles']} candles novos importados.");

            return self::SUCCESS;
        }

        $this->error('Use export ou import.');

        return self::FAILURE;
    }
}
