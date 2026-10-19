<?php

use App\Enums\BillingType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_products', function (Blueprint $table) {
            $table->string('billing_type', 20)->default(BillingType::OneTime->value)->index()->after('status');
            $table->string('billing_interval', 20)->nullable()->after('billing_type');
            $table->unsignedSmallInteger('access_duration_days')->nullable()->after('billing_interval');
            $table->unsignedSmallInteger('grace_days')->default(3)->after('access_duration_days');
        });
    }

    public function down(): void
    {
        Schema::table('store_products', function (Blueprint $table) {
            $table->dropColumn(['billing_type', 'billing_interval', 'access_duration_days', 'grace_days']);
        });
    }
};
