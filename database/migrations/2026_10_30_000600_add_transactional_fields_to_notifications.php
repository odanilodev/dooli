<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->change();
            $table->string('title')->nullable()->after('type_notification_id');
            $table->text('message')->nullable()->after('title');
            $table->string('action_url', 500)->nullable()->after('message');
            $table->string('category', 40)->nullable()->index()->after('action_url');
            $table->string('severity', 20)->default('info')->after('category');
            $table->string('dedupe_key', 191)->nullable()->after('severity');
            $table->json('metadata')->nullable()->after('dedupe_key');

            $table->unique(['company_id', 'dedupe_key'], 'notifications_company_dedupe_unique');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropUnique('notifications_company_dedupe_unique');
            $table->dropColumn([
                'title',
                'message',
                'action_url',
                'category',
                'severity',
                'dedupe_key',
                'metadata',
            ]);
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable(false)->change();
        });
    }
};
