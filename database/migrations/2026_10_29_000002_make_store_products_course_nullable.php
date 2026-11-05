<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Produto "só a ferramenta": a turma libera um robô e não tem curso.
        Schema::table('store_products', function (Blueprint $table) {
            $table->unsignedBigInteger('course_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('store_products', function (Blueprint $table) {
            $table->unsignedBigInteger('course_id')->nullable(false)->change();
        });
    }
};
