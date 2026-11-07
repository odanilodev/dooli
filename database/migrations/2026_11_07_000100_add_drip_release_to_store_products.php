<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_products', function (Blueprint $table) {
            $table->boolean('drip_enabled')->default(false)->after('grace_days');
        });

        Schema::create('store_product_module_drips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('store_product_id')->constrained('store_products')->cascadeOnDelete();
            $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
            $table->unsignedSmallInteger('interval_days')->default(1);
            $table->timestamps();

            $table->unique(['store_product_id', 'module_id']);
        });

        // Compras novas guardam qual produto liberou o acesso gradual; NULL = acesso total.
        Schema::table('access_grants', function (Blueprint $table) {
            $table->foreignId('drip_product_id')->nullable()->after('source_id')
                ->constrained('store_products')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('access_grants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('drip_product_id');
        });

        Schema::dropIfExists('store_product_module_drips');

        Schema::table('store_products', function (Blueprint $table) {
            $table->dropColumn('drip_enabled');
        });
    }
};
