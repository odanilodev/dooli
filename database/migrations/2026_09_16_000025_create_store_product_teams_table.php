<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_product_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('store_product_id')->constrained('store_products');
            $table->foreignId('team_id')->constrained('teams');
            $table->string('status', 20)->index();
            $table->timestamps();

            $table->unique(['company_id', 'store_product_id', 'team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_product_teams');
    }
};
