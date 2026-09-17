<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timeline', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('module_id')->constrained('modules');
            $table->foreignId('class_id')->constrained('classes');
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('action_id')->constrained('actions');
            $table->foreignId('team_id')->constrained('teams');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timeline');
    }
};
