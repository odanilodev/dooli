<?php

namespace Tests\Feature;

use App\Enums\BillingType;
use App\Enums\GeneralStatus;
use App\Enums\SellerCommissionStatus;
use App\Models\Coupon;
use App\Models\Gateway;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Seller;
use App\Models\SellerCommission;
use App\Models\StoreProduct;
use App\Models\Subscription;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class SellerCommissionTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();
        config()->set('services.manual.webhook_secret', 'test-secret');
    }

    public function test_payment_creates_commission_per_item_with_discount_allocation_and_is_idempotent(): void
    {
        $seller = $this->seller(10);
        $first = $this->productWithTeam('Produto A', 100);
        $second = $this->productWithTeam('Produto B', 50);

        Coupon::factory()->create([
            'company_id' => $this->company->id,
            'code' => 'DESCONTO30',
            'value' => 30,
        ]);

        $order = $this->createOrder(
            [$first, $second],
            'DESCONTO30',
            $seller->code,
        );

        $this->assertSame('120.00', $order->total);

        $payment = $this->createPayment($order);
        $this->confirmPaymentWebhook($payment, 'evt-commission-1');

        $commissions = SellerCommission::query()
            ->with('orderItem')
            ->orderBy('id')
            ->get()
            ->keyBy(fn (SellerCommission $commission) => $commission->orderItem->product_title);

        $this->assertCount(2, $commissions);
        $this->assertSame('80.00', $commissions['Produto A']->base_amount);
        $this->assertSame('8.00', $commissions['Produto A']->amount);
        $this->assertSame('40.00', $commissions['Produto B']->base_amount);
        $this->assertSame('4.00', $commissions['Produto B']->amount);
        $this->assertSame(SellerCommissionStatus::Approved->value, $commissions['Produto A']->status);

        $this->confirmPaymentWebhook($payment, 'evt-commission-1');

        $this->assertSame(2, SellerCommission::query()->count());
    }

    public function test_partial_and_full_refunds_adjust_commission(): void
    {
        $seller = $this->seller(10);
        $product = $this->productWithTeam('Curso', 100);
        $order = $this->createOrder([$product], null, $seller->code);
        $payment = $this->createPayment($order);

        $this->confirmPaymentWebhook($payment, 'evt-refund-1');

        $commission = SellerCommission::query()->firstOrFail();

        $this->assertSame('10.00', $commission->amount);

        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/payments/'.$payment->id.'/refund', [
                'amount' => 40,
                'reason' => 'Estorno parcial',
            ])
            ->assertOk();

        $commission->refresh();

        $this->assertSame('4.00', $commission->refunded_amount);
        $this->assertSame(6.0, $commission->netAmount());
        $this->assertSame(SellerCommissionStatus::Approved->value, $commission->status);

        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/payments/'.$payment->id.'/refund', [
                'amount' => 60,
                'reason' => 'Estorno total',
            ])
            ->assertOk();

        $commission->refresh();

        $this->assertSame('10.00', $commission->refunded_amount);
        $this->assertSame(0.0, $commission->netAmount());
        $this->assertSame(SellerCommissionStatus::Reversed->value, $commission->status);
        $this->assertNotNull($commission->reversed_at);
    }

    public function test_admin_can_mark_commission_as_paid_and_view_seller_report(): void
    {
        $seller = $this->seller(10);
        $product = $this->productWithTeam('Curso', 100);

        $firstOrder = $this->createOrder([$product], null, $seller->code);
        $firstPayment = $this->createPayment($firstOrder);
        $this->confirmPaymentWebhook($firstPayment, 'evt-paid-1');

        $firstCommission = SellerCommission::query()->firstOrFail();

        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/commissions/'.$firstCommission->id.'/pay', [
                'payment_reference' => 'PIX 123',
                'payment_notes' => 'Pagamento semanal',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', SellerCommissionStatus::Paid->value)
            ->assertJsonPath('data.payment_reference', 'PIX 123');

        $secondOrder = $this->createOrder([$product], null, $seller->code);
        $secondPayment = $this->createPayment($secondOrder);
        $this->confirmPaymentWebhook($secondPayment, 'evt-paid-2');

        $secondCommission = SellerCommission::query()
            ->where('status', SellerCommissionStatus::Approved->value)
            ->firstOrFail();

        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/commissions/bulk-pay', [
                'ids' => [$secondCommission->id],
                'payment_reference' => 'PIX 124',
            ])
            ->assertOk()
            ->assertJsonPath('data.updated', 1);

        $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/sellers/'.$seller->id)
            ->assertOk()
            ->assertJsonPath('data.metrics.paid_orders_count', 2)
            ->assertJsonPath('data.metrics.students_count', 1)
            ->assertJsonPath('data.metrics.commission_paid', 20)
            ->assertJsonPath('data.products.0.product_title', 'Curso')
            ->assertJsonPath('data.products.0.orders_count', 2)
            ->assertJsonPath('data.recent_orders.0.id', $secondOrder->id);
    }

    public function test_subscription_renewals_generate_commission_with_frozen_seller(): void
    {
        $seller = $this->seller(10);
        $product = $this->productWithTeam('Assinatura', 100, BillingType::Subscription->value);
        $gateway = $this->gateway();
        $token = $this->apiLogin($this->student);

        $subscriptionId = $this->withToken($token)
            ->postJson('/api/v1/me/subscriptions', [
                'store_product_id' => $product->id,
                'gateway_id' => $gateway->id,
                'referral_code' => $seller->code,
            ])
            ->assertCreated()
            ->json('data.subscription.id');

        $subscription = Subscription::query()->findOrFail($subscriptionId);

        $this->postSubscriptionWebhook($subscription, 'sub-charge-1');
        $this->postSubscriptionWebhook($subscription, 'sub-charge-2');

        $commissions = SellerCommission::query()->orderBy('id')->get();

        $this->assertCount(2, $commissions);
        $this->assertTrue($commissions->every(
            fn (SellerCommission $commission) => $commission->seller_id === $seller->id,
        ));
        $this->assertTrue($commissions->every(
            fn (SellerCommission $commission) => $commission->amount === '10.00',
        ));
    }

    private function seller(float $percent): Seller
    {
        return Seller::factory()->create([
            'company_id' => $this->company->id,
            'commission_percent' => $percent,
        ]);
    }

    private function productWithTeam(
        string $title,
        float $price,
        string $billingType = BillingType::OneTime->value,
    ): StoreProduct {
        $course = $this->buildCourse($this->company->id);
        $team = Team::factory()->create([
            'company_id' => $this->company->id,
            'status' => GeneralStatus::Active->value,
        ]);
        $team->courses()->attach($course['course']->id, [
            'company_id' => $this->company->id,
            'status' => GeneralStatus::Active->value,
        ]);

        $product = StoreProduct::factory()->create([
            'company_id' => $this->company->id,
            'course_id' => $course['course']->id,
            'title' => $title,
            'price' => $price,
            'sale' => 0,
            'status' => GeneralStatus::Active->value,
            'release_at' => null,
            'billing_type' => $billingType,
            'billing_interval' => 'month',
        ]);

        $product->teams()->attach($team->id, [
            'company_id' => $this->company->id,
            'status' => GeneralStatus::Active->value,
        ]);

        return $product;
    }

    /**
     * @param  array<int, StoreProduct>  $products
     */
    private function createOrder(array $products, ?string $couponCode, string $referralCode): Order
    {
        $items = array_map(
            fn (StoreProduct $product) => ['store_product_id' => $product->id, 'quantity' => 1],
            $products,
        );

        $orderId = $this->withToken($this->apiLogin($this->student))
            ->postJson('/api/v1/me/orders', [
                'items' => $items,
                'coupon_code' => $couponCode,
                'referral_code' => $referralCode,
            ])
            ->assertCreated()
            ->json('data.id');

        return Order::query()->findOrFail($orderId);
    }

    private function gateway(): Gateway
    {
        return Gateway::query()->first()
            ?? Gateway::factory()->create([
                'company_id' => $this->company->id,
                'code' => 'manual',
            ]);
    }

    private function createPayment(Order $order): Payment
    {
        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/payments', [
                'order_id' => $order->id,
                'gateway_id' => $this->gateway()->id,
                'method' => 'pix',
                'amount' => (float) $order->total,
            ])
            ->assertCreated();

        return Payment::query()->where('order_id', $order->id)->latest('id')->firstOrFail();
    }

    private function confirmPaymentWebhook(Payment $payment, string $eventId): void
    {
        $this->withHeaders([
            'X-Company' => (string) $this->company->id,
            'X-Webhook-Secret' => 'test-secret',
        ])->postJson('/api/v1/webhooks/payments/manual', [
            'event_id' => $eventId,
            'event' => 'payment.updated',
            'status' => 'paid',
            'transaction_id' => $payment->transaction_id,
        ])->assertStatus(202);
    }

    private function postSubscriptionWebhook(Subscription $subscription, string $transactionId): void
    {
        $this->withHeaders([
            'X-Company' => (string) $this->company->id,
            'X-Webhook-Secret' => 'test-secret',
        ])->postJson('/api/v1/webhooks/companies/'.$this->company->slug.'/subscriptions/manual', [
            'provider_subscription_id' => $subscription->provider_subscription_id,
            'status' => 'paid',
            'transaction_id' => $transactionId,
        ])->assertStatus(202);
    }
}
