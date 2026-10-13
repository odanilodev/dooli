<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_lesson_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('team_id')->constrained('teams');
            $table->foreignId('course_id')->constrained('courses');
            $table->foreignId('module_id')->constrained('modules');
            $table->foreignId('lesson_id')->constrained('lessons');
            $table->string('status', 20);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('position_seconds')->default(0);
            $table->unsignedInteger('watched_seconds')->default(0);
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['user_id', 'team_id', 'course_id', 'lesson_id'],
                'student_lesson_progress_unique',
            );

            $table->index(
                ['company_id', 'team_id', 'course_id', 'module_id', 'lesson_id', 'status'],
                'slp_company_team_course_module_lesson_status_index',
            );
            $table->index(
                ['company_id', 'user_id', 'course_id', 'team_id', 'status'],
                'slp_company_user_course_team_status_index',
            );
            $table->index(
                ['company_id', 'lesson_id', 'team_id', 'status'],
                'slp_company_lesson_team_status_index',
            );
            $table->index(['company_id', 'last_activity_at'], 'slp_company_last_activity_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_lesson_progress');
    }
};
