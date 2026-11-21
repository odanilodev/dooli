<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Volume financeiro nativo da fonte (cripto: "quote volume" da Binance, em USDT).
     * Nulo nos mercados que só informam quantidade: ali o robô estima volume * fechamento.
     */
    public function up(): void
    {
        Schema::table('market_candles', function (Blueprint $table) {
            $table->decimal('quote_volume', 24, 2)->nullable()->after('volume');
        });
    }

    public function down(): void
    {
        Schema::table('market_candles', function (Blueprint $table) {
            $table->dropColumn('quote_volume');
        });
    }
};
