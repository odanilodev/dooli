<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_progresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('lesson_id')->constrained('lessons');
            $table->foreignId('course_id')->constrained('courses');
            $table->foreignId('team_id')->constrained('teams');
            $table->unsignedInteger('position_seconds')->default(0);
            $table->unsignedInteger('duration')->nullable();
            $table->timestamps();

            $table->unique(
                ['user_id', 'lesson_id', 'course_id', 'team_id'],
                'video_progresses_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_progresses');
    }
};
