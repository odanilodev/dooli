<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses');
            $table->unsignedInteger('required_lessons')->default(0);
            $table->json('lesson_ids')->nullable();
            $table->timestamp('completed_at');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'team_id', 'course_id'], 'course_completions_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_completions');
    }
};
