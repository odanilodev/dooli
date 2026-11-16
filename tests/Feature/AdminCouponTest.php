<?php

namespace Tests\Feature;

use App\Enums\CouponType;
use App\Models\Coupon;
use App\Models\StoreProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class AdminCouponTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();
    }

    public function test_admin_creates_coupon_with_validity_window(): void
    {
        $starts = now()->addDay()->startOfMinute();
        $expires = now()->addDays(10)->startOfMinute();

        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/coupons', [
                'code' => 'BLACKFRIDAY',
                'type' => CouponType::Percent->value,
                'value' => 15,
                'starts_at' => $starts->toIso8601String(),
                'expires_at' => $expires->toIso8601String(),
                'usage_limit' => 100,
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'BLACKFRIDAY')
            ->assertJsonPath('data.expires_at', $expires->toIso8601String());

        $this->assertDatabaseHas('coupons', ['code' => 'BLACKFRIDAY', 'company_id' => $this->company->id]);
    }

    public function test_expiration_before_start_is_rejected(): void
    {
        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/coupons', [
                'code' => 'ERRADO',
                'type' => CouponType::Fixed->value,
                'value' => 10,
                'starts_at' => now()->addDays(5)->toIso8601String(),
                'expires_at' => now()->addDay()->toIso8601String(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('expires_at');
    }

    public function test_percent_coupon_cannot_exceed_one_hundred(): void
    {
        $token = $this->apiLogin($this->admin);

        $this->withToken($token)
            ->postJson('/api/v1/admin/coupons', [
                'code' => 'DEMAIS',
                'type' => CouponType::Percent->value,
                'value' => 150,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('value');

        $coupon = Coupon::factory()->create([
            'company_id' => $this->company->id,
            'type' => CouponType::Percent->value,
            'value' => 10,
        ]);

        $this->withToken($token)
            ->putJson("/api/v1/admin/coupons/{$coupon->id}", ['value' => 101])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('value');
    }

    public function test_fixed_coupon_may_exceed_one_hundred(): void
    {
        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/coupons', [
                'code' => 'GRANDE',
                'type' => CouponType::Fixed->value,
                'value' => 250,
            ])
            ->assertCreated();
    }

    public function test_admin_can_set_expiration_on_existing_coupon_and_it_blocks_orders(): void
    {
        $course = $this->buildCourse($this->company->id);
        $storeProduct = StoreProduct::factory()->create([
            'company_id' => $this->company->id,
            'course_id' => $course['course']->id,
            'price' => 100,
            'sale' => 0,
        ]);

        $coupon = Coupon::factory()->create([
            'company_id' => $this->company->id,
            'code' => 'TEMPO',
            'type' => CouponType::Fixed->value,
            'value' => 10,
        ]);

        $this->withToken($this->apiLogin($this->admin))
            ->putJson("/api/v1/admin/coupons/{$coupon->id}", [
                'expires_at' => now()->subMinute()->toIso8601String(),
            ])
            ->assertOk();

        $this->withToken($this->apiLogin($this->student))
            ->postJson('/api/v1/me/orders', [
                'items' => [['store_product_id' => $storeProduct->id, 'quantity' => 1]],
                'coupon_code' => 'TEMPO',
            ])
            ->assertStatus(422);
    }
}
