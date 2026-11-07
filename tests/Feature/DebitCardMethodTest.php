<?php

namespace Tests\Feature;

use App\Models\Gateway;
use App\Models\Payment;
use App\Models\StoreProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class DebitCardMethodTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    private function payWithCard(string $paymentTypeId, string $methodId): void
    {
        Http::fake([
            'api.mercadopago.com/v1/payments' => Http::response([
                'id' => 123456,
                'status' => 'rejected',
                'status_detail' => 'cc_rejected_other_reason',
                'payment_type_id' => $paymentTypeId,
                'payment_method_id' => $methodId,
            ], 201),
        ]);

        $product = StoreProduct::factory()->create(['company_id' => $this->company->id, 'price' => 100]);
        $gateway = Gateway::factory()->create([
            'company_id' => $this->company->id,
            'code' => 'mercadopago',
            'credentials' => ['access_token' => 'TEST-token'],
        ]);

        $token = $this->apiLogin($this->student);

        $orderId = $this->withToken($token)->postJson('/api/v1/me/orders', [
            'items' => [['store_product_id' => $product->id, 'quantity' => 1]],
        ])->json('data.id');

        $this->withToken($token)->postJson("/api/v1/me/orders/{$orderId}/pay", [
            'gateway_id' => $gateway->id,
            'method' => 'credit_card',
            'card_token' => 'tok_123',
            'card_payment_method_id' => $methodId,
            'installments' => 1,
        ]);
    }

    public function test_debit_card_payment_is_recorded_as_debit(): void
    {
        $this->buildTenant();

        $this->payWithCard('debit_card', 'debmaster');

        $this->assertSame('debit_card', Payment::query()->firstOrFail()->method);
    }

    public function test_credit_card_payment_stays_credit(): void
    {
        $this->buildTenant();

        $this->payWithCard('credit_card', 'visa');

        $this->assertSame('credit_card', Payment::query()->firstOrFail()->method);
    }
}
