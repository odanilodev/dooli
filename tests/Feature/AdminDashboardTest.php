<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\StoreProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();
    }

    private function seedSales(): array
    {
        $course = $this->buildCourse($this->company->id);
        $this->enroll($this->student, $course['course'], $this->company->id);

        $product = StoreProduct::factory()->create([
            'company_id' => $this->company->id,
            'course_id' => $course['course']->id,
            'price' => 100,
            'sale' => 0,
        ]);

        $paidRecent = $this->order($product, OrderStatus::Paid, 100, now()->subDays(2));
        $paidOld = $this->order($product, OrderStatus::Paid, 30, now()->subDays(20));
        $refunded = $this->order($product, OrderStatus::Refunded, 50, now()->subDays(1));

        Payment::create([
            'company_id' => $this->company->id,
            'order_id' => $paidRecent->id,
            'status' => PaymentStatus::Paid->value,
            'amount' => 100,
            'paid_at' => now()->subDays(2),
        ]);

        Payment::create([
            'company_id' => $this->company->id,
            'order_id' => $paidOld->id,
            'status' => PaymentStatus::Paid->value,
            'amount' => 30,
            'paid_at' => now()->subDays(20),
        ]);

        $refundedPayment = Payment::create([
            'company_id' => $this->company->id,
            'order_id' => $refunded->id,
            'status' => PaymentStatus::Refunded->value,
            'amount' => 50,
            'paid_at' => now()->subDays(1),
        ]);

        Refund::create([
            'company_id' => $this->company->id,
            'payment_id' => $refundedPayment->id,
            'amount' => 50,
            'status' => RefundStatus::Refunded->value,
            'refunded_at' => now()->subDays(1),
        ]);

        return compact('course', 'product');
    }

    private function order(StoreProduct $product, OrderStatus $status, float $total, $createdAt): Order
    {
        $order = Order::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->student->id,
            'status' => $status->value,
            'subtotal' => $total,
            'total' => $total,
        ]);

        $order->created_at = $createdAt;
        $order->save();

        OrderItem::create([
            'company_id' => $this->company->id,
            'order_id' => $order->id,
            'store_product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => $total,
            'total' => $total,
        ]);

        return $order;
    }

    public function test_admin_can_view_the_dashboard(): void
    {
        $this->seedSales();

        $response = $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.period', '30');

        $this->assertEquals(1, $response->json('data.kpis.students_total'));
        $this->assertEquals(1, $response->json('data.kpis.students_new'));
        $this->assertEquals(130, $response->json('data.kpis.revenue'));
        $this->assertEquals(2, $response->json('data.kpis.orders_paid'));
        $this->assertEquals(3, $response->json('data.kpis.orders_total'));
        $this->assertEquals(65, $response->json('data.kpis.avg_ticket'));

        $this->assertEquals(2, $response->json('data.top_courses.0.sales'));
        $this->assertEquals(130, $response->json('data.top_courses.0.revenue'));
        $this->assertEquals($this->student->id, $response->json('data.top_buyers.0.id'));
        $this->assertEquals(2, $response->json('data.top_buyers.0.orders_count'));
        $this->assertEquals($this->student->id, $response->json('data.recent_students.0.id'));
        $this->assertEquals(1, $response->json('data.students_per_course.0.students_count'));
    }

    public function test_period_filter_changes_the_numbers(): void
    {
        $this->seedSales();

        $week = $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/dashboard?period=7')
            ->assertOk();

        $this->assertEquals(100, $week->json('data.kpis.revenue'));
        $this->assertEquals(1, $week->json('data.kpis.orders_paid'));

        $all = $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/dashboard?period=all')
            ->assertOk();

        $this->assertEquals(130, $all->json('data.kpis.revenue'));
    }

    public function test_order_status_breakdown_is_returned(): void
    {
        $this->seedSales();

        $response = $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/dashboard')
            ->assertOk();

        $statuses = collect($response->json('data.order_status'))->pluck('count', 'status');

        $this->assertEquals(2, $statuses[OrderStatus::Paid->value]);
        $this->assertEquals(1, $statuses[OrderStatus::Refunded->value]);
        $this->assertEquals(0, $statuses[OrderStatus::Pending->value]);
    }

    public function test_student_cannot_view_the_dashboard(): void
    {
        $this->withToken($this->apiLogin($this->student))
            ->getJson('/api/v1/admin/dashboard')
            ->assertStatus(403);
    }

    public function test_invalid_period_is_rejected(): void
    {
        $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/dashboard?period=999')
            ->assertStatus(422);
    }

    public function test_custom_date_range_filters_metrics(): void
    {
        $this->seedSales();

        $recent = $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/dashboard?from='.now()->subDays(3)->toDateString().'&to='.now()->toDateString())
            ->assertOk()
            ->assertJsonPath('data.period', 'custom');

        $this->assertEquals(100, $recent->json('data.kpis.revenue'));
        $this->assertEquals(1, $recent->json('data.kpis.orders_paid'));

        $older = $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/dashboard?from='.now()->subDays(25)->toDateString().'&to='.now()->subDays(10)->toDateString())
            ->assertOk();

        $this->assertEquals(30, $older->json('data.kpis.revenue'));
        $this->assertEquals(1, $older->json('data.kpis.orders_paid'));
    }

    public function test_from_without_to_is_rejected(): void
    {
        $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/dashboard?from='.now()->subDays(3)->toDateString())
            ->assertStatus(422);
    }

    public function test_to_before_from_is_rejected(): void
    {
        $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/dashboard?from='.now()->toDateString().'&to='.now()->subDays(3)->toDateString())
            ->assertStatus(422);
    }
}
