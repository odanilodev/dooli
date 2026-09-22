<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('team_id')->constrained('teams');
            $table->foreignId('course_id')->constrained('courses');
            $table->string('status', 20)->index();
            $table->timestamps();

            $table->unique(['company_id', 'team_id', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_courses');
    }
};
