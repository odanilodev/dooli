<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->string('title');
            $table->string('image')->nullable();
            $table->unsignedInteger('time')->default(0);
            $table->string('status', 20)->index();
            $table->string('player')->nullable();
            $table->string('link')->nullable();
            $table->string('code')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};
