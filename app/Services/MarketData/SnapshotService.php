<?php

namespace App\Services\MarketData;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Snapshot dos candles diários em CSV comprimido, para levar a base de mercado de um
 * ambiente a outro (dev -> prod) sem refazer a coleta nas APIs.
 *
 * A importação só insere o que falta (ativo+data); nunca sobrescreve candle já coletado.
 */
class SnapshotService
{
    public const DEFAULT_PATH = 'database/data/market_candles.csv.gz';

    private const COLUMNS = ['market', 'symbol', 'name', 'date', 'open', 'high', 'low', 'close', 'volume'];

    public function export(string $path): int
    {
        $handle = gzopen($path, 'wb9');

        if ($handle === false) {
            throw new RuntimeException("Não foi possível gravar {$path}.");
        }

        gzwrite($handle, $this->line(self::COLUMNS));

        $rows = 0;

        $query = DB::table('market_candles as c')
            ->join('market_assets as a', 'a.id', '=', 'c.market_asset_id')
            ->orderBy('a.market')->orderBy('a.symbol')->orderBy('c.date')
            ->select('a.market', 'a.symbol', 'a.name', 'c.date', 'c.open', 'c.high', 'c.low', 'c.close', 'c.volume');

        foreach ($query->cursor() as $r) {
            gzwrite($handle, $this->line([
                $r->market, $r->symbol, $r->name, substr((string) $r->date, 0, 10),
                $r->open, $r->high, $r->low, $r->close, $r->volume,
            ]));
            $rows++;
        }

        gzclose($handle);

        return $rows;
    }

    /** @return array{assets: int, candles: int} registros efetivamente inseridos */
    public function import(string $path): array
    {
        $handle = gzopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Arquivo não encontrado: {$path}.");
        }

        $header = str_getcsv((string) gzgets($handle));

        if ($header !== self::COLUMNS) {
            throw new RuntimeException('Formato de snapshot inválido.');
        }

        $assetsBefore = DB::table('market_assets')->count();
        $candlesBefore = DB::table('market_candles')->count();

        $assetIds = [];
        $buffer = [];
        $now = now();

        $flush = function () use (&$buffer): void {
            if ($buffer !== []) {
                DB::table('market_candles')->insertOrIgnore($buffer);
                $buffer = [];
            }
        };

        while (($line = gzgets($handle)) !== false) {
            $row = str_getcsv(rtrim($line, "\r\n"));

            if (count($row) !== count(self::COLUMNS)) {
                continue;
            }

            [$market, $symbol, $name, $date, $open, $high, $low, $close, $volume] = $row;
            $key = $market.'|'.$symbol;

            if (! isset($assetIds[$key])) {
                DB::table('market_assets')->insertOrIgnore([
                    'market' => $market, 'symbol' => $symbol, 'name' => $name !== '' ? $name : null,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                $assetIds[$key] = (int) DB::table('market_assets')->where('market', $market)->where('symbol', $symbol)->value('id');
            }

            $buffer[] = [
                'market_asset_id' => $assetIds[$key], 'date' => $date,
                'open' => $open, 'high' => $high, 'low' => $low, 'close' => $close, 'volume' => $volume,
                'created_at' => $now, 'updated_at' => $now,
            ];

            if (count($buffer) >= 1000) {
                $flush();
            }
        }

        $flush();
        gzclose($handle);

        // insertOrIgnore não informa de forma confiável quantas linhas entraram; a diferença sim.
        return [
            'assets' => DB::table('market_assets')->count() - $assetsBefore,
            'candles' => DB::table('market_candles')->count() - $candlesBefore,
        ];
    }

    private function line(array $values): string
    {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, $values);
        rewind($out);
        $line = stream_get_contents($out);
        fclose($out);

        return $line;
    }
}
