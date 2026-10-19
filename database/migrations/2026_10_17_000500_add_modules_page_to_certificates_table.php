<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->string('modules_placement', 20)->default('front')->after('layout');
            $table->string('modules_title')->nullable()->after('modules_placement');
            $table->json('modules_layout')->nullable()->after('modules_title');
            $table->string('modules_background')->nullable()->after('modules_layout');
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropColumn([
                'modules_placement',
                'modules_title',
                'modules_layout',
                'modules_background',
            ]);
        });
    }
};
