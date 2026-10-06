<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->foreignId('module_id')->nullable()->after('name')->constrained('modules');
        });

        DB::statement('UPDATE lessons SET module_id = (SELECT module_id FROM topics WHERE topics.id = lessons.topic_id)');

        Schema::table('lessons', function (Blueprint $table) {
            $table->foreignId('module_id')->nullable(false)->change();
            $table->foreignId('topic_id')->nullable()->change();
            $table->index(['module_id', 'status', 'sort_order'], 'lessons_module_status_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropIndex('lessons_module_status_sort_idx');
            $table->dropConstrainedForeignId('module_id');
            $table->foreignId('topic_id')->nullable(false)->change();
        });
    }
};
