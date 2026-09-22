<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The Gateway model casts "credentials" as encrypted:array, which stores a
        // base64 payload. A JSON column cannot hold that value, so it must be text.
        Schema::table('gateways', function (Blueprint $table) {
            $table->text('credentials')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('gateways', function (Blueprint $table) {
            $table->json('credentials')->nullable()->change();
        });
    }
};
