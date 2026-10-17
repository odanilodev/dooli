<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificate_users', function (Blueprint $table) {
            $table->json('modules')->nullable()->after('lessons_count');
        });
    }

    public function down(): void
    {
        Schema::table('certificate_users', function (Blueprint $table) {
            $table->dropColumn('modules');
        });
    }
};
