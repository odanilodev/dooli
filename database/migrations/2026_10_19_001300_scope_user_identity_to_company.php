<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_unique');
            $table->dropUnique('users_username_unique');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unique(['company_id', 'email']);
            $table->unique(['company_id', 'username']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'email']);
            $table->dropUnique(['company_id', 'username']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unique('email', 'users_email_unique');
            $table->unique('username', 'users_username_unique');
        });
    }
};
