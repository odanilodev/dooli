<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('actions', function (Blueprint $table) {
            $table->string('code', 50)->nullable()->after('name');
            $table->unique(['company_id', 'code'], 'actions_company_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('actions', function (Blueprint $table) {
            $table->dropUnique('actions_company_code_unique');
            $table->dropColumn('code');
        });
    }
};
