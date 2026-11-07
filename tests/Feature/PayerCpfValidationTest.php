<?php

namespace Tests\Feature;

use App\Models\Gateway;
use App\Models\StoreProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class PayerCpfValidationTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    private int $orderId;

    private int $gatewayId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();

        $product = StoreProduct::factory()->create(['company_id' => $this->company->id, 'price' => 100]);
        $this->gatewayId = Gateway::factory()->create(['company_id' => $this->company->id, 'code' => 'manual'])->id;

        $this->orderId = $this->withToken($this->apiLogin($this->student))->postJson('/api/v1/me/orders', [
            'items' => [['store_product_id' => $product->id, 'quantity' => 1]],
        ])->json('data.id');
    }

    private function pay(string $number, string $type = 'CPF')
    {
        return $this->withToken($this->apiLogin($this->student))->postJson("/api/v1/me/orders/{$this->orderId}/pay", [
            'gateway_id' => $this->gatewayId,
            'method' => 'pix',
            'payer' => ['identification' => ['type' => $type, 'number' => $number]],
        ]);
    }

    public function test_rejects_an_invalid_payer_cpf(): void
    {
        $this->pay('123.456.789-00')
            ->assertStatus(422)
            ->assertJsonValidationErrors('payer.identification.number');
    }

    public function test_rejects_a_repeated_digits_cpf(): void
    {
        $this->pay('11111111111')->assertStatus(422);
    }

    public function test_accepts_a_valid_payer_cpf(): void
    {
        $this->pay('529.982.247-25')->assertCreated();
    }
}
