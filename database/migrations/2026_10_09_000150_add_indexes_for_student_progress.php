<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timelines', function (Blueprint $table) {
            $table->index(
                ['user_id', 'action_id', 'course_id', 'team_id'],
                'timelines_user_action_course_team_index',
            );
        });

        Schema::table('video_progresses', function (Blueprint $table) {
            $table->index(
                ['user_id', 'course_id', 'team_id', 'position_seconds'],
                'video_progresses_user_course_team_position_index',
            );
        });

        Schema::table('course_modules', function (Blueprint $table) {
            $table->index(['course_id', 'status'], 'course_modules_course_status_index');
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->index(['module_id', 'status'], 'lessons_module_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('timelines', function (Blueprint $table) {
            $table->dropIndex('timelines_user_action_course_team_index');
        });

        Schema::table('video_progresses', function (Blueprint $table) {
            $table->dropIndex('video_progresses_user_course_team_position_index');
        });

        Schema::table('course_modules', function (Blueprint $table) {
            $table->dropIndex('course_modules_course_status_index');
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->dropIndex('lessons_module_status_index');
        });
    }
};
