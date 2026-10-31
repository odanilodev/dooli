<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_mail_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained('companies')->cascadeOnDelete();
            $table->string('provider', 30)->default('resend');
            $table->string('status', 20)->default('active')->index();
            $table->string('from_name')->nullable();
            $table->string('from_address')->nullable();
            $table->string('reply_to')->nullable();
            $table->string('email_domain')->nullable()->index();
            $table->string('region', 30)->default('sa-east-1');
            $table->string('domain_id')->nullable();
            $table->string('domain_status', 30)->default('not_started')->index();
            $table->json('dns_records')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->text('last_error')->nullable();
            $table->text('credentials')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_mail_settings');
    }
};
