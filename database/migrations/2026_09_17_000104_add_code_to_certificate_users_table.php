<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificate_users', function (Blueprint $table) {
            $table->string('code', 64)->nullable()->after('id');
            $table->unique('code', 'certificate_users_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('certificate_users', function (Blueprint $table) {
            $table->dropUnique('certificate_users_code_unique');
            $table->dropColumn('code');
        });
    }
};
