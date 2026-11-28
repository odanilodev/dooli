<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Preferências de cada usuário numa ferramenta (ex.: regras de ativos operáveis do
        // DeltaStats). Sem linha aqui, a ferramenta usa o padrão definido no código.
        Schema::create('robot_user_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('robot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('settings');
            $table->timestamps();

            $table->unique(['robot_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('robot_user_settings');
    }
};
