<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_store_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();
            $table->foreignId('store_product_id')->constrained('store_products')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'coupon_id', 'store_product_id'], 'coupon_store_products_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_store_products');
    }
};
