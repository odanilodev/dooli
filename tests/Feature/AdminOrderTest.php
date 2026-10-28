<?php

namespace Tests\Feature;

use App\Enums\OrderSource;
use App\Models\Company;
use App\Models\Order;
use App\Models\StoreProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
    }
}
