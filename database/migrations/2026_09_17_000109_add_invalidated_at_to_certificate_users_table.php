<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificate_users', function (Blueprint $table) {
            $table->timestamp('invalidated_at')->nullable()->after('created_at');
            $table->string('invalidation_reason')->nullable()->after('invalidated_at');
        });
    }

    public function down(): void
    {
        Schema::table('certificate_users', function (Blueprint $table) {
            $table->dropColumn(['invalidated_at', 'invalidation_reason']);
        });
    }
};
