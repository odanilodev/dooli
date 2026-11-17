<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Remove os dados da B3 de 26/10/2026 (segunda-feira após o 2º turno das eleições).
     *
     * Só toca a B3 e só esse dia: candles diários (market_candles) e candles de 15 minutos
     * (market_intraday_candles, gravados em UTC; o pregão em Brasília é UTC-3). Os ativos
     * em si e os outros mercados e dias não são alterados. É irreversível: faça backup antes.
     */
    private const DATE = '2026-10-26';

    public function up(): void
    {
        $b3Assets = fn () => DB::table('market_assets')->where('market', 'b3')->select('id');

        DB::table('market_candles')
            ->whereIn('market_asset_id', $b3Assets())
            ->whereDate('date', self::DATE)
            ->delete();

        // 26/10 00:00 a 23:59 em Brasília = 26/10 03:00 a 27/10 02:59 em UTC.
        DB::table('market_intraday_candles')
            ->whereIn('market_asset_id', $b3Assets())
            ->whereBetween('ts', [self::DATE.' 03:00:00', '2026-10-27 02:59:59'])
            ->delete();
    }

    public function down(): void
    {
        // Irreversível: os dados apagados não são recriados (a coleta não volta sozinha a um dia no meio do histórico).
    }
};
