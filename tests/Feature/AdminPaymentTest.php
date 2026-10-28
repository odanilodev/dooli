<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Gateway;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class AdminPaymentTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    private Gateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();

        $this->gateway = Gateway::factory()->create([
            'company_id' => $this->company->id,
            'code' => 'manual',
        ]);
    }

    public function test_admin_can_list_payments_with_order_and_user_without_raw_metadata(): void
    {
        $this->payment(PaymentStatus::Paid->value, 100, [
            'card' => ['cardholder' => ['name' => 'DADO SENSIVEL']],
        ]);

        $response = $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/payments')
            ->assertOk()
            ->assertJsonPath('data.0.order.user.name', $this->student->name)
            ->assertJsonPath('data.0.status', PaymentStatus::Paid->value)
            ->assertJsonMissingPath('data.0.metadata');

        $this->assertStringNotContainsString('DADO SENSIVEL', $response->getContent());
    }

    public function test_payment_summary_uses_refunds_and_statuses(): void
    {
        $this->payment(PaymentStatus::Paid->value, 100);
        $this->payment(PaymentStatus::Pending->value, 50, [], OrderStatus::Pending->value);
        $this->payment(PaymentStatus::Failed->value, 20, [], OrderStatus::Pending->value);

        $refundedPayment = $this->payment(
            PaymentStatus::Refunded->value,
            30,
            [],
            OrderStatus::Refunded->value,
        );

        Refund::query()->create([
            'company_id' => $this->company->id,
            'payment_id' => $refundedPayment->id,
            'amount' => 10,
            'reason' => 'Teste',
            'status' => RefundStatus::Refunded->value,
            'refunded_at' => now(),
        ]);

        $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/payments/summary')
            ->assertOk()
            ->assertJsonPath('data.gross', 130)
            ->assertJsonPath('data.refunded', 10)
            ->assertJsonPath('data.received', 120)
            ->assertJsonPath('data.pending', 50)
            ->assertJsonPath('data.failed', 20)
            ->assertJsonPath('data.counts.paid', 1);
    }

    public function test_admin_payment_detail_contains_events_and_refunds(): void
    {
        $payment = $this->payment(PaymentStatus::Refunded->value, 100, [], OrderStatus::Refunded->value);

        Refund::query()->create([
            'company_id' => $this->company->id,
            'payment_id' => $payment->id,
            'amount' => 100,
            'reason' => 'Estorno total',
            'status' => RefundStatus::Refunded->value,
            'refunded_at' => now(),
        ]);

        $this->withToken($this->apiLogin($this->admin))
            ->getJson("/api/v1/admin/payments/{$payment->id}")
            ->assertOk()
            ->assertJsonPath('data.refunds.0.amount', '100.00')
            ->assertJsonPath('data.order.user.name', $this->student->name)
            ->assertJsonMissingPath('data.metadata');
    }

    public function test_student_cannot_access_admin_payments(): void
    {
        $this->withToken($this->apiLogin($this->student))
            ->getJson('/api/v1/admin/payments')
            ->assertStatus(403);

        $this->withToken($this->apiLogin($this->student))
            ->getJson('/api/v1/admin/payments/summary')
            ->assertStatus(403);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function payment(
        string $status,
        float $amount,
        array $metadata = [],
        string $orderStatus = OrderStatus::Paid->value,
    ): Payment {
        $order = Order::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->student->id,
            'status' => $orderStatus,
            'subtotal' => $amount,
            'total' => $amount,
        ]);

        return Payment::query()->create([
            'company_id' => $this->company->id,
            'order_id' => $order->id,
            'gateway_id' => $this->gateway->id,
            'method' => 'credit_card',
            'transaction_id' => 'txn-'.uniqid(),
            'status' => $status,
            'amount' => $amount,
            'paid_at' => $status === PaymentStatus::Paid->value ? now() : null,
            'metadata' => $metadata,
        ]);
    }
}
