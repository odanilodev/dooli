<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('subscription_id')
                ->nullable()
                ->after('coupon_id')
                ->constrained('subscriptions')
                ->nullOnDelete();
            $table->timestamp('period_start')->nullable()->after('total');
            $table->timestamp('period_end')->nullable()->after('period_start');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subscription_id');
            $table->dropColumn(['period_start', 'period_end']);
        });
    }
};
