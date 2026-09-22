<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses');
            $table->foreignId('company_id')->constrained('companies');
            $table->string('title');
            $table->string('image')->nullable();
            $table->text('description')->nullable();
            $table->string('slug');
            $table->json('tags')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('sale', 10, 2)->default(0);
            $table->string('status', 20)->index();
            $table->timestamp('release_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_products');
    }
};
