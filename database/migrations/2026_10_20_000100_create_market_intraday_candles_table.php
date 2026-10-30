<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Candles de 15 minutos guardados localmente: as análises leem daqui e
        // não dependem das APIs externas na hora. `ts` é o início do candle em UTC.
        Schema::create('market_intraday_candles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('market_asset_id')->constrained('market_assets')->cascadeOnDelete();
            $table->dateTime('ts');
            $table->decimal('open', 18, 6);
            $table->decimal('high', 18, 6);
            $table->decimal('low', 18, 6);
            $table->decimal('close', 18, 6);
            $table->decimal('volume', 20, 2)->default(0);
            $table->timestamps();

            $table->unique(['market_asset_id', 'ts']);
            $table->index('ts');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_intraday_candles');
    }
};
