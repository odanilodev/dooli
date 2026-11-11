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

    public function test_coupon_global_usage_limit_is_enforced(): void
    {
        $course = $this->buildCourse($this->company->id);
        $storeProduct = StoreProduct::factory()->create([
            'company_id' => $this->company->id,
            'course_id' => $course['course']->id,
            'price' => 100,
        ]);

        $coupon = Coupon::factory()->create([
            'company_id' => $this->company->id,
            'code' => 'ULTIMO',
            'type' => CouponType::Fixed->value,
            'value' => 5,
            'usage_limit' => 1,
        ]);

        $payload = [
            'items' => [['store_product_id' => $storeProduct->id, 'quantity' => 1]],
            'coupon_code' => 'ULTIMO',
        ];

        $this->withToken($this->apiLogin($this->student))->postJson('/api/v1/me/orders', $payload)->assertCreated();
        $this->withToken($this->apiLogin($this->student))->postJson('/api/v1/me/orders', $payload)->assertStatus(422);

        $this->assertSame(1, $coupon->fresh()->usage_count);
    }

    public function test_canceling_an_order_releases_the_coupon_usage(): void
    {
        $course = $this->buildCourse($this->company->id);
        $storeProduct = StoreProduct::factory()->create([
            'company_id' => $this->company->id,
            'course_id' => $course['course']->id,
            'price' => 100,
        ]);

        $coupon = Coupon::factory()->create([
            'company_id' => $this->company->id,
            'code' => 'DEVOLVE',
            'type' => CouponType::Fixed->value,
            'value' => 5,
            'usage_limit' => 1,
        ]);

        $token = $this->apiLogin($this->student);

        $orderId = $this->withToken($token)->postJson('/api/v1/me/orders', [
            'items' => [['store_product_id' => $storeProduct->id, 'quantity' => 1]],
            'coupon_code' => 'DEVOLVE',
        ])->assertCreated()->json('data.id');

        $this->assertSame(1, $coupon->fresh()->usage_count);

        $this->withToken($token)->postJson("/api/v1/me/orders/{$orderId}/cancel")->assertOk();

        $this->assertSame(0, $coupon->fresh()->usage_count);

        // O uso devolvido permite um novo pedido com o mesmo cupom.
        $this->withToken($token)->postJson('/api/v1/me/orders', [
            'items' => [['store_product_id' => $storeProduct->id, 'quantity' => 1]],
            'coupon_code' => 'DEVOLVE',
        ])->assertCreated();
    }
}
