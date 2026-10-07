<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->morphs('schedulable');
            $table->timestamp('available_at');
            $table->timestamps();

            $table->unique(
                ['company_id', 'team_id', 'schedulable_type', 'schedulable_id'],
                'schedules_team_target_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
