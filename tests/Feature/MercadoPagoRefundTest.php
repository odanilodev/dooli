<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Gateway;
use App\Models\Order;
use App\Models\Payment;
use App\Models\StoreProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class MercadoPagoRefundTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    private int $paymentId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();

        $product = StoreProduct::factory()->create(['company_id' => $this->company->id, 'price' => 100]);
        $gateway = Gateway::factory()->create([
            'company_id' => $this->company->id,
            'code' => 'mercadopago',
            'credentials' => ['access_token' => 'TEST-token'],
        ]);

        $orderId = $this->withToken($this->apiLogin($this->student))->postJson('/api/v1/me/orders', [
            'items' => [['store_product_id' => $product->id, 'quantity' => 1]],
        ])->json('data.id');

        $payment = Payment::query()->create([
            'company_id' => $this->company->id,
            'order_id' => $orderId,
            'gateway_id' => $gateway->id,
            'method' => 'credit_card',
            'status' => PaymentStatus::Paid->value,
            'amount' => 100,
            'transaction_id' => '987654',
            'paid_at' => now(),
        ]);
        Order::query()->whereKey($orderId)->update(['status' => OrderStatus::Paid->value]);

        $this->paymentId = $payment->id;
    }

    public function test_refund_failure_exposes_the_gateway_reason(): void
    {
        Http::fake([
            'api.mercadopago.com/v1/payments/987654/refunds' => Http::response([
                'message' => 'Not enough balance to refund',
                'error' => 'bad_request',
            ], 400),
        ]);

        $this->withToken($this->apiLogin($this->admin))
            ->postJson("/api/v1/admin/payments/{$this->paymentId}/refund", ['amount' => 100])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Falha ao solicitar estorno. Motivo: Not enough balance to refund']);
    }

    public function test_refund_request_sends_an_idempotency_key(): void
    {
        Http::fake([
            'api.mercadopago.com/v1/payments/987654/refunds' => Http::response(['id' => 1, 'amount' => 100], 201),
        ]);

        $this->withToken($this->apiLogin($this->admin))
            ->postJson("/api/v1/admin/payments/{$this->paymentId}/refund", ['amount' => 100])
            ->assertOk();

        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/refunds')
            && str_starts_with((string) $request->header('X-Idempotency-Key')[0], 'refund-'.$this->paymentId.'-'));
    }
}
