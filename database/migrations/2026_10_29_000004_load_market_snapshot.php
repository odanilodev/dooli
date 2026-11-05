<?php

use App\Services\MarketData\SnapshotService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $path = base_path(SnapshotService::DEFAULT_PATH);

        // Testes montam seus próprios dados; o snapshot é só para ambientes reais.
        if (app()->environment('testing') || ! is_file($path)) {
            return;
        }

        // Só insere o que falta, então é seguro onde a coleta já rodou.
        app(SnapshotService::class)->import($path);
    }

    public function down(): void
    {
        // Os candles ficam: apagá-los desfaria a coleta de quem já usa o robô.
    }
};
