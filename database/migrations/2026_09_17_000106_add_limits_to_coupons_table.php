<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->unsignedInteger('per_user_limit')->nullable()->after('usage_count');
            $table->decimal('min_order_amount', 10, 2)->default(0)->after('per_user_limit');
            $table->decimal('max_discount', 10, 2)->nullable()->after('min_order_amount');
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn(['per_user_limit', 'min_order_amount', 'max_discount']);
        });
    }
};
