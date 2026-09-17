<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('permission_menu')->nullable();
            $table->string('status', 20)->index();
            $table->foreignId('company_id')->constrained('companies');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_types');
    }
};
