<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Gateway;
use App\Models\Order;
use App\Models\Payment;
use App\Models\StoreProduct;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class SplitPaymentTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    private int $orderId;

    private int $gatewayId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();

        $product = StoreProduct::factory()->create(['company_id' => $this->company->id, 'price' => 300]);
        $this->gatewayId = Gateway::factory()->create(['company_id' => $this->company->id, 'code' => 'manual'])->id;

        $this->orderId = $this->withToken($this->apiLogin($this->student))->postJson('/api/v1/me/orders', [
            'items' => [['store_product_id' => $product->id, 'quantity' => 1]],
        ])->json('data.id');
    }

    private function pay(float $amount)
    {
        return $this->withToken($this->apiLogin($this->student))->postJson("/api/v1/me/orders/{$this->orderId}/pay", [
            'gateway_id' => $this->gatewayId,
            'method' => 'pix',
            'amount' => $amount,
            'payer' => ['identification' => ['type' => 'CPF', 'number' => '529.982.247-25']],
        ]);
    }

    private function confirm(int $paymentId): void
    {
        app(PaymentService::class)->confirm(Payment::query()->findOrFail($paymentId));
    }

    public function test_a_partial_payment_creates_a_charge_for_that_amount_only(): void
    {
        $this->pay(100)->assertCreated()->assertJsonPath('data.amount', '100.00');

        $this->assertSame(1, Payment::query()->where('order_id', $this->orderId)->count());
    }

    public function test_rejects_an_amount_above_the_remaining_balance(): void
    {
        $this->pay(300.01)->assertStatus(422);
        $this->assertSame(0, Payment::query()->where('order_id', $this->orderId)->count());
    }

    public function test_order_stays_pending_until_the_sum_of_payments_covers_the_total(): void
    {
        $first = $this->pay(100)->json('data.id');
        $this->confirm($first);

        $order = Order::query()->findOrFail($this->orderId);
        $this->assertSame(OrderStatus::Pending->value, $order->status);

        $this->withToken($this->apiLogin($this->student))
            ->getJson("/api/v1/me/orders/{$this->orderId}")
            ->assertOk()
            ->assertJsonPath('data.paid_amount', 100)
            ->assertJsonPath('data.remaining_amount', 200);

        // A segunda parte não pode passar do que falta.
        $this->pay(250)->assertStatus(422);

        $second = $this->pay(200)->json('data.id');
        $this->confirm($second);

        $this->assertSame(OrderStatus::Paid->value, $order->refresh()->status);
    }

    public function test_pay_without_amount_charges_the_remaining_balance(): void
    {
        $this->confirm($this->pay(120)->json('data.id'));

        $this->withToken($this->apiLogin($this->student))
            ->postJson("/api/v1/me/orders/{$this->orderId}/pay", [
                'gateway_id' => $this->gatewayId,
                'method' => 'pix',
                'payer' => ['identification' => ['type' => 'CPF', 'number' => '529.982.247-25']],
            ])
            ->assertCreated()
            ->assertJsonPath('data.amount', '180.00');
    }

    public function test_student_refund_returns_every_paid_part_of_the_order(): void
    {
        $first = $this->pay(100)->json('data.id');
        $this->confirm($first);
        $second = $this->pay(200)->json('data.id');
        $this->confirm($second);

        $this->withToken($this->apiLogin($this->student))
            ->postJson("/api/v1/me/orders/{$this->orderId}/refund")
            ->assertOk();

        $this->assertSame(OrderStatus::Refunded->value, Order::query()->findOrFail($this->orderId)->status);
        $this->assertSame(2, Payment::query()
            ->where('order_id', $this->orderId)
            ->where('status', PaymentStatus::Refunded->value)
            ->count());
    }

    public function test_student_can_refund_the_paid_part_of_an_incomplete_order(): void
    {
        // O PIX (parte 1) foi aprovado, o cartão (parte 2) falhou: o pedido segue pendente.
        $this->confirm($this->pay(100)->json('data.id'));

        $token = $this->apiLogin($this->student);

        $this->withToken($token)
            ->getJson("/api/v1/me/orders/{$this->orderId}")
            ->assertJsonPath('data.refund.eligible', true)
            ->assertJsonPath('data.refund.partial', true);

        $this->withToken($token)->postJson("/api/v1/me/orders/{$this->orderId}/refund")->assertOk();

        // O dinheiro volta, mas o pedido continua pendente para o aluno tentar pagar de novo.
        $order = Order::query()->findOrFail($this->orderId);
        $this->assertSame(OrderStatus::Pending->value, $order->status);
        $this->assertSame(0.0, app(PaymentService::class)->paidAmount($order));
        $this->assertSame(1, Payment::query()
            ->where('order_id', $this->orderId)
            ->where('status', PaymentStatus::Refunded->value)
            ->count());

        $this->pay(300)->assertCreated();
    }

    public function test_refunding_one_part_keeps_the_order_while_another_part_is_still_paid(): void
    {
        $first = $this->pay(100)->json('data.id');
        $this->confirm($first);
        $second = $this->pay(200)->json('data.id');
        $this->confirm($second);

        app(PaymentService::class)->refund(Payment::query()->findOrFail($first));

        $this->assertSame(OrderStatus::Paid->value, Order::query()->findOrFail($this->orderId)->status);
    }
}
