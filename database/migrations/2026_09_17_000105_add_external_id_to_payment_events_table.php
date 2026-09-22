<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_events', function (Blueprint $table) {
            $table->string('external_id')->nullable()->after('event');
            $table->unique(['payment_id', 'external_id'], 'payment_events_payment_external_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payment_events', function (Blueprint $table) {
            $table->dropUnique('payment_events_payment_external_unique');
            $table->dropColumn('external_id');
        });
    }
};
