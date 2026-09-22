<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_types', function (Blueprint $table) {
            $table->string('code', 50)->nullable()->after('name');
            $table->unique(['company_id', 'code'], 'user_types_company_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('user_types', function (Blueprint $table) {
            $table->dropUnique('user_types_company_code_unique');
            $table->dropColumn('code');
        });
    }
};
