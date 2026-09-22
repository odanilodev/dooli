<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timelines', function (Blueprint $table) {
            $table->index(['user_id', 'course_id', 'lesson_id', 'action_id'], 'timeline_user_course_lesson_action_idx');
            $table->index(['company_id', 'team_id', 'lesson_id'], 'timeline_company_team_lesson_idx');
        });

        Schema::table('team_users', function (Blueprint $table) {
            $table->index(['company_id', 'user_id', 'status'], 'team_users_company_user_status_idx');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index(['company_id', 'user_id', 'status'], 'orders_company_user_status_idx');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index(['company_id', 'order_id', 'status'], 'payments_company_order_status_idx');
        });

        Schema::table('payment_events', function (Blueprint $table) {
            $table->index(['payment_id', 'event'], 'payment_events_payment_event_idx');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['company_id', 'team_id', 'status'], 'notifications_company_team_status_idx');
        });

        Schema::table('certificate_users', function (Blueprint $table) {
            $table->index(['company_id', 'user_id'], 'cert_users_company_user_idx');
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->index(['topic_id', 'status', 'sort_order'], 'lessons_topic_status_sort_idx');
        });

        Schema::table('store_products', function (Blueprint $table) {
            $table->index(['company_id', 'status', 'release_at'], 'store_products_company_status_release_idx');
        });
    }

    public function down(): void
    {
        Schema::table('timelines', function (Blueprint $table) {
            $table->dropIndex('timeline_user_course_lesson_action_idx');
            $table->dropIndex('timeline_company_team_lesson_idx');
        });

        Schema::table('team_users', function (Blueprint $table) {
            $table->dropIndex('team_users_company_user_status_idx');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_company_user_status_idx');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_company_order_status_idx');
        });

        Schema::table('payment_events', function (Blueprint $table) {
            $table->dropIndex('payment_events_payment_event_idx');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_company_team_status_idx');
        });

        Schema::table('certificate_users', function (Blueprint $table) {
            $table->dropIndex('cert_users_company_user_idx');
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->dropIndex('lessons_topic_status_sort_idx');
        });

        Schema::table('store_products', function (Blueprint $table) {
            $table->dropIndex('store_products_company_status_release_idx');
        });
    }
};
