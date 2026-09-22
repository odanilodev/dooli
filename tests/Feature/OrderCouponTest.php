<?php

namespace Tests\Feature;

use App\Enums\CouponType;
use App\Models\Coupon;
use App\Models\StoreProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class OrderCouponTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();
    }

    public function test_order_applies_coupon_discount_recalculated_on_backend(): void
    {
        $course = $this->buildCourse($this->company->id);
        $storeProduct = StoreProduct::factory()->create([
            'company_id' => $this->company->id,
            'course_id' => $course['course']->id,
            'price' => 100,
            'sale' => 0,
        ]);

        Coupon::factory()->create([
            'company_id' => $this->company->id,
            'code' => 'DESCONTO10',
            'type' => CouponType::Fixed->value,
            'value' => 10,
        ]);

        $this->withToken($this->apiLogin($this->student))
            ->postJson('/api/v1/me/orders', [
                'items' => [['store_product_id' => $storeProduct->id, 'quantity' => 1]],
                'coupon_code' => 'DESCONTO10',
            ])
            ->assertCreated()
            ->assertJsonPath('data.subtotal', '100.00')
            ->assertJsonPath('data.discount', '10.00')
            ->assertJsonPath('data.total', '90.00');
    }

    public function test_coupon_per_user_limit_is_enforced(): void
    {
        $course = $this->buildCourse($this->company->id);
        $storeProduct = StoreProduct::factory()->create([
            'company_id' => $this->company->id,
            'course_id' => $course['course']->id,
            'price' => 100,
        ]);

        Coupon::factory()->create([
            'company_id' => $this->company->id,
            'code' => 'ONCE',
            'type' => CouponType::Fixed->value,
            'value' => 5,
            'per_user_limit' => 1,
        ]);

        $token = $this->apiLogin($this->student);

        $this->withToken($token)->postJson('/api/v1/me/orders', [
            'items' => [['store_product_id' => $storeProduct->id, 'quantity' => 1]],
            'coupon_code' => 'ONCE',
        ])->assertCreated();

        $this->withToken($token)->postJson('/api/v1/me/orders', [
            'items' => [['store_product_id' => $storeProduct->id, 'quantity' => 1]],
            'coupon_code' => 'ONCE',
        ])->assertStatus(422);
    }

    public function test_student_cannot_read_another_students_order(): void
    {
        $course = $this->buildCourse($this->company->id);
        $storeProduct = StoreProduct::factory()->create([
            'company_id' => $this->company->id,
            'course_id' => $course['course']->id,
            'price' => 50,
        ]);

        $orderId = $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/me/orders', [
                'items' => [['store_product_id' => $storeProduct->id, 'quantity' => 1]],
            ])
            ->json('data.id');

        $this->withToken($this->apiLogin($this->student))
            ->getJson("/api/v1/me/orders/{$orderId}")
            ->assertStatus(403);
    }
}
