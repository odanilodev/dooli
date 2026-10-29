<?php

namespace Tests\Feature;

use App\Enums\BillingType;
use App\Enums\GeneralStatus;
use App\Models\Company;
use App\Models\Gateway;
use App\Models\Order;
use App\Models\Seller;
use App\Models\StoreProduct;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class SellerAttributionTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();
        Notification::fake();
    }

    public function test_registration_stores_the_referring_seller(): void
    {
        $seller = Seller::factory()->create([
            'company_id' => $this->company->id,
            'code' => 'joao123',
        ]);

        $this->postJson('/api/v1/public/companies/'.$this->company->slug.'/auth/register', [
            'name' => 'Novo Aluno',
            'email' => 'indicado@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => true,
            'referral_code' => 'JOAO123',
        ])->assertCreated();

        $user = User::query()->where('email', 'indicado@example.com')->firstOrFail();

        $this->assertSame($seller->id, $user->referred_seller_id);
        $this->assertTrue($user->referral_expires_at->isFuture());
    }

    public function test_order_uses_explicit_referral_code_and_freezes_commission(): void
    {
        $seller = Seller::factory()->create([
            'company_id' => $this->company->id,
            'code' => 'joao123',
            'commission_percent' => 15,
        ]);
        $product = $this->product(100);

        $orderId = $this->withToken($this->apiLogin($this->student))
            ->postJson('/api/v1/me/orders', [
                'items' => [['store_product_id' => $product->id, 'quantity' => 1]],
                'referral_code' => 'joao123',
            ])
            ->assertCreated()
            ->json('data.id');

        $order = Order::query()->findOrFail($orderId);

        $this->assertSame($seller->id, $order->seller_id);
        $this->assertSame('joao123', $order->referral_code);
        $this->assertSame('15.00', $order->commission_percent);
        $this->assertSame($seller->id, $this->student->fresh()->referred_seller_id);
    }

    public function test_order_falls_back_to_the_users_valid_attribution(): void
    {
        $seller = Seller::factory()->create(['company_id' => $this->company->id]);
        $product = $this->product(100);

        $this->student->update([
            'referred_seller_id' => $seller->id,
            'referral_expires_at' => now()->addDay(),
        ]);

        $orderId = $this->withToken($this->apiLogin($this->student))
            ->postJson('/api/v1/me/orders', [
                'items' => [['store_product_id' => $product->id, 'quantity' => 1]],
            ])
            ->assertCreated()
            ->json('data.id');

        $this->assertSame($seller->id, Order::query()->findOrFail($orderId)->seller_id);
    }

    public function test_expired_user_attribution_is_ignored(): void
    {
        $seller = Seller::factory()->create(['company_id' => $this->company->id]);
        $product = $this->product(100);

        $this->student->update([
            'referred_seller_id' => $seller->id,
            'referral_expires_at' => now()->subMinute(),
        ]);

        $orderId = $this->withToken($this->apiLogin($this->student))
            ->postJson('/api/v1/me/orders', [
                'items' => [['store_product_id' => $product->id, 'quantity' => 1]],
            ])
            ->assertCreated()
            ->json('data.id');

        $this->assertNull(Order::query()->findOrFail($orderId)->seller_id);
    }

    public function test_last_valid_referral_code_wins_over_stored_seller(): void
    {
        $first = Seller::factory()->create([
            'company_id' => $this->company->id,
            'code' => 'primeiro',
        ]);
        $second = Seller::factory()->create([
            'company_id' => $this->company->id,
            'code' => 'segundo',
        ]);
        $product = $this->product(100);

        $this->student->update([
            'referred_seller_id' => $first->id,
            'referral_expires_at' => now()->addDay(),
        ]);

        $orderId = $this->withToken($this->apiLogin($this->student))
            ->postJson('/api/v1/me/orders', [
                'items' => [['store_product_id' => $product->id, 'quantity' => 1]],
                'referral_code' => 'segundo',
            ])
            ->assertCreated()
            ->json('data.id');

        $this->assertSame($second->id, Order::query()->findOrFail($orderId)->seller_id);
        $this->assertSame($second->id, $this->student->fresh()->referred_seller_id);
    }

    public function test_inactive_or_foreign_seller_code_is_ignored(): void
    {
        $inactive = Seller::factory()->inactive()->create([
            'company_id' => $this->company->id,
            'code' => 'inativo',
        ]);
        $foreign = Seller::factory()->create([
            'company_id' => Company::factory()->create()->id,
            'code' => 'externo',
        ]);
        $product = $this->product(100);
        $token = $this->apiLogin($this->student);

        $inactiveOrderId = $this->withToken($token)
            ->postJson('/api/v1/me/orders', [
                'items' => [['store_product_id' => $product->id, 'quantity' => 1]],
                'referral_code' => $inactive->code,
            ])
            ->assertCreated()
            ->json('data.id');

        $foreignOrderId = $this->withToken($token)
            ->postJson('/api/v1/me/orders', [
                'items' => [['store_product_id' => $product->id, 'quantity' => 1]],
                'referral_code' => $foreign->code,
            ])
            ->assertCreated()
            ->json('data.id');

        $this->assertNull(Order::query()->findOrFail($inactiveOrderId)->seller_id);
        $this->assertNull(Order::query()->findOrFail($foreignOrderId)->seller_id);
    }

    public function test_subscription_start_records_seller_and_frozen_commission(): void
    {
        $seller = Seller::factory()->create([
            'company_id' => $this->company->id,
            'code' => 'assinante',
            'commission_percent' => 8.5,
        ]);
        $product = StoreProduct::factory()->create([
            'company_id' => $this->company->id,
            'billing_type' => BillingType::Subscription->value,
            'billing_interval' => 'month',
            'status' => GeneralStatus::Active->value,
            'release_at' => null,
        ]);
        $gateway = Gateway::factory()->create([
            'company_id' => $this->company->id,
            'code' => 'manual',
        ]);

        $this->withToken($this->apiLogin($this->student))
            ->postJson('/api/v1/me/subscriptions', [
                'store_product_id' => $product->id,
                'gateway_id' => $gateway->id,
                'referral_code' => 'assinante',
            ])
            ->assertCreated();

        $subscription = Subscription::query()->firstOrFail();

        $this->assertSame($seller->id, $subscription->seller_id);
        $this->assertSame('assinante', $subscription->referral_code);
        $this->assertSame('8.50', $subscription->commission_percent);
    }

    private function product(float $price): StoreProduct
    {
        return StoreProduct::factory()->create([
            'company_id' => $this->company->id,
            'price' => $price,
            'sale' => 0,
            'status' => GeneralStatus::Active->value,
            'release_at' => null,
        ]);
    }
}
