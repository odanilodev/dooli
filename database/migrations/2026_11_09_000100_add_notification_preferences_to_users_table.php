<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('notify_newsletter')->default(true)->after('city');
            $table->boolean('notify_lesson_reminders')->default(true)->after('notify_newsletter');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['notify_newsletter', 'notify_lesson_reminders']);
        });
    }
};
