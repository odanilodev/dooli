<?php

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('store_product_id')->constrained('store_products');
            $table->foreignId('gateway_id')->nullable()->constrained('gateways')->nullOnDelete();
            $table->string('provider_subscription_id')->nullable()->index();
            $table->string('status', 20)->default(SubscriptionStatus::Pending->value)->index();
            $table->decimal('amount', 10, 2)->default(0);
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('grace_ends_at')->nullable();
            $table->timestamp('next_billing_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'user_id', 'store_product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
