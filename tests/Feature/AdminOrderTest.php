<?php

namespace Tests\Feature;

use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\StoreProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class AdminOrderTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();
    }

    private function createStoreProduct(): StoreProduct
    {
        $course = $this->buildCourse($this->company->id);

        return StoreProduct::factory()->create([
            'company_id' => $this->company->id,
            'course_id' => $course['course']->id,
            'price' => 100,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function order(array $attributes = []): Order
    {
        return Order::factory()->create(array_merge([
            'company_id' => $this->company->id,
            'user_id' => $this->student->id,
            'status' => OrderStatus::Paid->value,
            'source' => OrderSource::Store->value,
            'subtotal' => 100,
            'discount' => 0,
            'total' => 100,
        ], $attributes));
    }

    private function payment(Order $order, float $amount, string $method = 'credit_card'): Payment
    {
        return Payment::query()->create([
            'company_id' => $this->company->id,
            'order_id' => $order->id,
            'gateway_id' => null,
            'method' => $method,
            'transaction_id' => 'txn-'.uniqid(),
            'status' => PaymentStatus::Paid->value,
            'amount' => $amount,
            'paid_at' => now(),
        ]);
    }

    private function refund(Order $order, float $amount): Refund
    {
        $payment = $order->payments()->firstOrFail();

        return Refund::query()->create([
            'company_id' => $this->company->id,
            'payment_id' => $payment->id,
            'amount' => $amount,
            'reason' => 'Estorno parcial',
            'status' => RefundStatus::Refunded->value,
            'refunded_at' => now(),
        ]);
    }

    public function test_admin_can_list_company_orders(): void
    {
        $storeProduct = $this->createStoreProduct();

        $this->withToken($this->apiLogin($this->student))
            ->postJson('/api/v1/me/orders', [
                'items' => [['store_product_id' => $storeProduct->id, 'quantity' => 1]],
            ])
            ->assertCreated();

        $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/orders')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.user.name', $this->student->name)
            ->assertJsonPath('data.0.user.registration_source', $this->student->registration_source)
            ->assertJsonPath('data.0.type', 'one_time')
            ->assertJsonPath('data.0.source', OrderSource::Store->value);
    }

    public function test_admin_can_filter_orders_by_source(): void
    {
        $this->order(['source' => OrderSource::Store->value]);
        $this->order(['source' => OrderSource::Manual->value]);

        $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/orders?source=manual')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.source', OrderSource::Manual->value);
    }

    public function test_sales_summary_uses_paid_orders_and_refunds(): void
    {
        $store = $this->order([
            'source' => OrderSource::Store->value,
            'total' => 100,
            'subtotal' => 100,
        ]);
        $this->payment($store, 100);
        $this->refund($store, 20);

        $this->order([
            'source' => OrderSource::Store->value,
            'total' => 200,
            'subtotal' => 200,
        ]);

        $manual = $this->order([
            'source' => OrderSource::Manual->value,
            'total' => 300,
            'subtotal' => 300,
        ]);
        $this->payment($manual, 300);

        $this->order([
            'source' => OrderSource::Store->value,
            'status' => OrderStatus::Pending->value,
            'total' => 50,
            'subtotal' => 50,
        ]);

        $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/orders/summary?source=store')
            ->assertOk()
            ->assertJsonPath('data.paid_orders', 2)
            ->assertJsonPath('data.revenue', 300)
            ->assertJsonPath('data.avg_ticket', 150)
            ->assertJsonPath('data.refunded', 20);
    }

    public function test_sales_export_returns_filtered_xlsx(): void
    {
        $product = $this->createStoreProduct();
        $order = $this->order([
            'source' => OrderSource::Store->value,
            'total' => 100,
            'subtotal' => 100,
        ]);

        OrderItem::query()->create([
            'company_id' => $this->company->id,
            'order_id' => $order->id,
            'store_product_id' => $product->id,
            'product_title' => 'Curso de PHP',
            'product_access_label' => 'Acesso permanente',
            'quantity' => 1,
            'unit_price' => 100,
            'total' => 100,
        ]);

        $this->payment($order, 100, 'pix');
        $this->order([
            'source' => OrderSource::Manual->value,
            'total' => 200,
            'subtotal' => 200,
        ]);

        $response = $this->withToken($this->apiLogin($this->admin))
            ->get('/api/v1/admin/orders/export?source=store')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $content = $response->streamedContent();
        $this->assertStringStartsWith('PK', $content);

        $path = tempnam(sys_get_temp_dir(), 'sales-');
        file_put_contents($path, $content);

        try {
            $sheet = IOFactory::load($path)->getActiveSheet();

            $this->assertSame('Pedido', $sheet->getCell('A1')->getValue());
            $this->assertSame('Produtos', $sheet->getCell('E1')->getValue());
            $this->assertSame('Método', $sheet->getCell('O1')->getValue());
            $this->assertEquals($order->id, $sheet->getCell('A2')->getValue());
            $this->assertSame('Curso de PHP (1)', $sheet->getCell('E2')->getValue());
            $this->assertSame('Loja', $sheet->getCell('G2')->getValue());
            $this->assertEquals(100, $sheet->getCell('L2')->getValue());
            $this->assertSame('Pago', $sheet->getCell('M2')->getValue());
            $this->assertSame('PIX', $sheet->getCell('O2')->getValue());
            $this->assertNull($sheet->getCell('A3')->getValue());
        } finally {
            @unlink($path);
        }
    }

    public function test_admin_can_show_an_order(): void
    {
        $storeProduct = $this->createStoreProduct();

        $orderId = $this->withToken($this->apiLogin($this->student))
            ->postJson('/api/v1/me/orders', [
                'items' => [['store_product_id' => $storeProduct->id, 'quantity' => 1]],
            ])
            ->json('data.id');

        $this->withToken($this->apiLogin($this->admin))
            ->getJson("/api/v1/admin/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.id', $orderId)
            ->assertJsonPath('data.user.name', $this->student->name)
            ->assertJsonPath('data.source', OrderSource::Store->value)
            ->assertJsonPath('data.timeline.0.type', 'order.created');
    }

    public function test_admin_cannot_show_an_order_from_another_company(): void
    {
        $otherOrder = Order::factory()->create(['company_id' => Company::factory()->create()->id]);

        // Cross-tenant access is hidden by the tenant scope (404, no data leak).
        $this->withToken($this->apiLogin($this->admin))
            ->getJson("/api/v1/admin/orders/{$otherOrder->id}")
            ->assertStatus(404);
    }

    public function test_student_cannot_access_admin_orders(): void
    {
        $this->withToken($this->apiLogin($this->student))
            ->getJson('/api/v1/admin/orders')
            ->assertStatus(403);

        $this->withToken($this->apiLogin($this->student))
            ->getJson('/api/v1/admin/orders/summary')
            ->assertStatus(403);

        $this->withToken($this->apiLogin($this->student))
            ->get('/api/v1/admin/orders/export')
            ->assertStatus(403);
    }
}
