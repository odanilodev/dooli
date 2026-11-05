<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Quais turmas liberam cada ferramenta. O aluno enxerga a ferramenta quando tem
        // vínculo ativo (compra, assinatura ou manual) com alguma turma daqui.
        Schema::create('robot_team', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('robot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['robot_id', 'team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('robot_team');
    }
};
