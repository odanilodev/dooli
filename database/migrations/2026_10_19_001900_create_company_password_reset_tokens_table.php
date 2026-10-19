<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_password_reset_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('email');
            $table->string('token');
            $table->timestamp('created_at')->nullable();

            $table->unique(['company_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_password_reset_tokens');
    }
};
