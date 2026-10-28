<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Gateway;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\Payment;
use App\Models\StoreProduct;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class OrderHistoryTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    private StoreProduct $product;

    private Gateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();

        $course = $this->buildCourse($this->company->id);
        $this->product = StoreProduct::factory()->create([
            'company_id' => $this->company->id,
            'course_id' => $course['course']->id,
            'price' => 100,
        ]);

        $team = $this->enroll($this->student, $course['course'], $this->company->id);
        $this->product->teams()->attach($team->id, [
            'company_id' => $this->company->id,
            'status' => 'active',
        ]);

        $this->gateway = Gateway::factory()->create([
            'company_id' => $this->company->id,
            'code' => 'manual',
        ]);
    }

    public function test_student_order_detail_has_timeline_and_safe_payment_details(): void
    {
        $studentToken = $this->apiLogin($this->student);
        $adminToken = $this->apiLogin($this->admin);

        $orderId = $this->withToken($studentToken)->postJson('/api/v1/me/orders', [
            'items' => [['store_product_id' => $this->product->id, 'quantity' => 1]],
        ])->json('data.id');

        $paymentId = $this->withToken($adminToken)->postJson('/api/v1/admin/payments', [
            'order_id' => $orderId,
            'gateway_id' => $this->gateway->id,
            'method' => 'credit_card',
            'amount' => 100,
        ])->assertCreated()->json('data.id');

        $payment = Payment::query()->findOrFail($paymentId);
        $payment->update([
            'metadata' => [
                'installments' => 3,
                'card' => [
                    'card_brand' => 'visa',
                    'last_four_digits' => '4242',
                    'cardholder' => ['name' => 'DADO SENSIVEL'],
                ],
                'transaction_details' => [
                    'installment_amount' => 33.33,
                ],
            ],
        ]);

        app(PaymentService::class)->applyWebhook($payment->id, [
            'external_id' => 'evt-history-paid',
            'event' => 'payment.updated',
            'status' => PaymentStatus::Paid->value,
            'transaction_id' => $payment->transaction_id,
            'paid_at' => '2026-10-20T12:30:00-03:00',
            'metadata' => [
                'id' => $payment->transaction_id,
                'status' => 'approved',
                'date_approved' => '2026-10-20T12:30:00-03:00',
                'installments' => 3,
            ],
        ]);

        $response = $this->withToken($studentToken)
            ->getJson("/api/v1/me/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.id', $orderId)
            ->assertJsonPath('data.payments.0.details.card_brand', 'visa')
            ->assertJsonPath('data.payments.0.details.card_last_four', '4242')
            ->assertJsonPath('data.payments.0.details.installments', 3)
            ->assertJsonMissingPath('data.payments.0.metadata')
            ->assertJsonMissingPath('data.timeline.0.actor_name');

        $this->assertStringNotContainsString('DADO SENSIVEL', $response->getContent());
        $this->assertStringStartsWith('2026-10-20T12:30:00', $response->json('data.payments.0.paid_at'));

        $types = collect($response->json('data.timeline'))->pluck('type');
        $this->assertTrue($types->contains('order.created'));
        $this->assertTrue($types->contains('payment.started'));
        $this->assertTrue($types->contains('payment.approved'));
        $this->assertTrue($types->contains('access.granted'));
    }

    public function test_order_item_keeps_product_title_snapshot(): void
    {
        $studentToken = $this->apiLogin($this->student);
        $originalTitle = $this->product->title;

        $orderId = $this->withToken($studentToken)->postJson('/api/v1/me/orders', [
            'items' => [['store_product_id' => $this->product->id, 'quantity' => 1]],
        ])->json('data.id');

        $this->product->update(['title' => 'Produto renomeado depois da compra']);

        $this->withToken($studentToken)
            ->getJson("/api/v1/me/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.items.0.product_title', $originalTitle);
    }

    public function test_legacy_orders_get_a_reconstructed_timeline(): void
    {
        $paid = Order::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->student->id,
            'status' => OrderStatus::Paid->value,
            'total' => 100,
        ]);

        Payment::query()->create([
            'company_id' => $this->company->id,
            'order_id' => $paid->id,
            'gateway_id' => $this->gateway->id,
            'method' => 'pix',
            'transaction_id' => 'legacy-paid',
            'status' => PaymentStatus::Paid->value,
            'amount' => 100,
            'paid_at' => now()->subDay(),
        ]);

        $canceled = Order::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->student->id,
            'status' => OrderStatus::Canceled->value,
        ]);

        $this->assertSame(0, OrderEvent::query()->whereIn('order_id', [$paid->id, $canceled->id])->count());

        $paidTimeline = $this->withToken($this->apiLogin($this->admin))
            ->getJson("/api/v1/admin/orders/{$paid->id}")
            ->assertOk()
            ->json('data.timeline');

        $paidTypes = collect($paidTimeline)->pluck('type');
        $this->assertTrue($paidTypes->contains('order.created'));
        $this->assertTrue($paidTypes->contains('payment.started'));
        $this->assertTrue($paidTypes->contains('payment.approved'));
        $this->assertTrue($paidTypes->contains('access.granted'));
        $this->assertTrue(collect($paidTimeline)->contains('estimated', true));

        $canceledTypes = collect(
            $this->withToken($this->apiLogin($this->admin))
                ->getJson("/api/v1/admin/orders/{$canceled->id}")
                ->assertOk()
                ->json('data.timeline'),
        )->pluck('type');

        $this->assertTrue($canceledTypes->contains('order.canceled'));
    }

    public function test_admin_refund_updates_order_timeline_and_payment_status(): void
    {
        $studentToken = $this->apiLogin($this->student);
        $adminToken = $this->apiLogin($this->admin);

        $orderId = $this->withToken($studentToken)->postJson('/api/v1/me/orders', [
            'items' => [['store_product_id' => $this->product->id, 'quantity' => 1]],
        ])->json('data.id');

        $paymentId = $this->withToken($adminToken)->postJson('/api/v1/admin/payments', [
            'order_id' => $orderId,
            'gateway_id' => $this->gateway->id,
            'method' => 'pix',
            'amount' => 100,
        ])->json('data.id');

        app(PaymentService::class)->applyWebhook($paymentId, [
            'external_id' => 'evt-history-refund',
            'event' => 'payment.updated',
            'status' => PaymentStatus::Paid->value,
            'transaction_id' => Payment::query()->findOrFail($paymentId)->transaction_id,
            'metadata' => [],
        ]);

        $this->withToken($adminToken)
            ->postJson("/api/v1/admin/payments/{$paymentId}/refund", [
                'amount' => 100,
                'reason' => 'Solicitação do aluno',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', PaymentStatus::Refunded->value);

        $this->assertSame(OrderStatus::Refunded->value, Order::query()->findOrFail($orderId)->status);

        $timeline = $this->withToken($adminToken)
            ->getJson("/api/v1/admin/orders/{$orderId}")
            ->assertOk()
            ->json('data.timeline');

        $types = collect($timeline)->pluck('type');
        $this->assertTrue($types->contains('payment.refunded'));
        $this->assertTrue($types->contains('order.refunded'));
    }

    public function test_webhook_cannot_resurrect_a_canceled_order(): void
    {
        $studentToken = $this->apiLogin($this->student);
        $adminToken = $this->apiLogin($this->admin);

        $orderId = $this->withToken($studentToken)->postJson('/api/v1/me/orders', [
            'items' => [['store_product_id' => $this->product->id, 'quantity' => 1]],
        ])->json('data.id');

        $paymentId = $this->withToken($adminToken)->postJson('/api/v1/admin/payments', [
            'order_id' => $orderId,
            'gateway_id' => $this->gateway->id,
            'method' => 'pix',
            'amount' => 100,
        ])->json('data.id');

        $this->withToken($studentToken)
            ->postJson("/api/v1/me/orders/{$orderId}/cancel")
            ->assertOk();

        app(PaymentService::class)->applyWebhook($paymentId, [
            'external_id' => 'evt-history-canceled',
            'event' => 'payment.updated',
            'status' => PaymentStatus::Paid->value,
            'transaction_id' => Payment::query()->findOrFail($paymentId)->transaction_id,
            'metadata' => [],
        ]);

        $this->assertSame(OrderStatus::Canceled->value, Order::query()->findOrFail($orderId)->status);
        $this->assertSame(PaymentStatus::Pending->value, Payment::query()->findOrFail($paymentId)->status);

        $types = collect(
            $this->withToken($adminToken)
                ->getJson("/api/v1/admin/orders/{$orderId}")
                ->assertOk()
                ->json('data.timeline'),
        )->pluck('type');

        $this->assertTrue($types->contains('payment.blocked'));
    }

    public function test_failed_webhook_is_recorded_in_the_timeline(): void
    {
        $studentToken = $this->apiLogin($this->student);
        $adminToken = $this->apiLogin($this->admin);

        $orderId = $this->withToken($studentToken)->postJson('/api/v1/me/orders', [
            'items' => [['store_product_id' => $this->product->id, 'quantity' => 1]],
        ])->json('data.id');

        $paymentId = $this->withToken($adminToken)->postJson('/api/v1/admin/payments', [
            'order_id' => $orderId,
            'gateway_id' => $this->gateway->id,
            'method' => 'pix',
            'amount' => 100,
        ])->json('data.id');

        app(PaymentService::class)->applyWebhook($paymentId, [
            'external_id' => 'evt-history-failed',
            'event' => 'payment.updated',
            'status' => PaymentStatus::Failed->value,
            'transaction_id' => Payment::query()->findOrFail($paymentId)->transaction_id,
            'metadata' => [],
        ]);

        $this->assertSame(PaymentStatus::Failed->value, Payment::query()->findOrFail($paymentId)->status);

        $types = collect(
            $this->withToken($studentToken)
                ->getJson("/api/v1/me/orders/{$orderId}")
                ->assertOk()
                ->json('data.timeline'),
        )->pluck('type');

        $this->assertTrue($types->contains('payment.failed'));
    }
}
