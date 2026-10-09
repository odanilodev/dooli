<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_live_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('team_id')->constrained('teams');
            $table->foreignId('live_class_id')->constrained('live_classes');
            $table->string('status', 20)->index();
            $table->timestamps();

            $table->unique(['company_id', 'team_id', 'live_class_id'], 'team_live_classes_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_live_classes');
    }
};
