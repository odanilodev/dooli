<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('referred_seller_id')
                ->nullable()
                ->after('company_id')
                ->constrained('sellers')
                ->nullOnDelete();
            $table->timestamp('referral_expires_at')->nullable()->after('referred_seller_id');

            $table->index(['company_id', 'referred_seller_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('seller_id')
                ->nullable()
                ->after('subscription_id')
                ->constrained('sellers')
                ->nullOnDelete();
            $table->string('referral_code', 32)->nullable()->after('seller_id');
            $table->decimal('commission_percent', 5, 2)->nullable()->after('referral_code');

            $table->index('seller_id');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreignId('seller_id')
                ->nullable()
                ->after('gateway_id')
                ->constrained('sellers')
                ->nullOnDelete();
            $table->string('referral_code', 32)->nullable()->after('seller_id');
            $table->decimal('commission_percent', 5, 2)->nullable()->after('referral_code');

            $table->index('seller_id');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['seller_id']);
            $table->dropConstrainedForeignId('seller_id');
            $table->dropColumn(['referral_code', 'commission_percent']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['seller_id']);
            $table->dropConstrainedForeignId('seller_id');
            $table->dropColumn(['referral_code', 'commission_percent']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'referred_seller_id']);
            $table->dropConstrainedForeignId('referred_seller_id');
            $table->dropColumn('referral_expires_at');
        });
    }
};
