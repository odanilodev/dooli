<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachment_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('class_id')->constrained('classes');
            $table->foreignId('attachment_id')->constrained('attachments');
            $table->string('status', 20)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'class_id', 'attachment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachment_classes');
    }
};
