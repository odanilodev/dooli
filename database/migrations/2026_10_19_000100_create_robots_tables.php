<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Catálogo de robôs liberados por empresa. O comportamento de cada robô
        // vive no código (RobotManager); aqui fica só o que a vitrine exibe.
        Schema::create('robots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('slug');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('market')->nullable();
            $table->string('icon')->nullable();
            $table->string('status')->default('active');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'slug']);
        });

        // Dados de mercado compartilhados entre empresas (não são do tenant).
        Schema::create('market_assets', function (Blueprint $table) {
            $table->id();
            $table->string('market');
            $table->string('symbol');
            $table->string('name')->nullable();
            $table->timestamps();

            $table->unique(['market', 'symbol']);
        });

        Schema::create('market_candles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('market_asset_id')->constrained('market_assets')->cascadeOnDelete();
            $table->date('date');
            $table->decimal('open', 18, 6);
            $table->decimal('high', 18, 6);
            $table->decimal('low', 18, 6);
            $table->decimal('close', 18, 6);
            $table->decimal('volume', 20, 2)->default(0);
            $table->timestamps();

            $table->unique(['market_asset_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_candles');
        Schema::dropIfExists('market_assets');
        Schema::dropIfExists('robots');
    }
};
