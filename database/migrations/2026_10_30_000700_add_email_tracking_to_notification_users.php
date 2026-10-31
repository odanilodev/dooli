<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_users', function (Blueprint $table) {
            $table->timestamp('email_sent_at')->nullable()->after('read_at');
            $table->string('email_status', 20)->nullable()->index()->after('email_sent_at');
            $table->text('email_error')->nullable()->after('email_status');
            $table->unsignedInteger('email_attempts')->default(0)->after('email_error');
            $table->string('email_message_id')->nullable()->after('email_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('notification_users', function (Blueprint $table) {
            $table->dropColumn([
                'email_sent_at',
                'email_status',
                'email_error',
                'email_attempts',
                'email_message_id',
            ]);
        });
    }
};
