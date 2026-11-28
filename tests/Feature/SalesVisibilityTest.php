<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Datalog;
use App\Models\Gateway;
use App\Models\Menu;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Seller;
use App\Models\StoreProduct;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

/**
 * Admin vê todas as vendas da empresa; os demais veem só as vendas do vendedor
 * vinculado ao próprio usuário.
 */
class SalesVisibilityTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    private StoreProduct $product;

    /** Usuário não admin, vinculado ao vendedor $ownSeller. */
    private User $sellerUser;

    private Seller $ownSeller;

    private Seller $otherSeller;

    private Order $ownOrder;

    private Order $otherOrder;

    private Order $unassignedOrder;

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

        $this->sellerUser = $this->staffUser();
        $this->ownSeller = Seller::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->sellerUser->id,
        ]);
        $this->otherSeller = Seller::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->staffUser()->id,
        ]);

        $this->ownOrder = $this->sale($this->ownSeller, 100);
        $this->otherOrder = $this->sale($this->otherSeller, 250);
        $this->unassignedOrder = $this->sale(null, 400);
    }

    public function test_admin_sees_every_sale(): void
    {
        $token = $this->apiLogin($this->admin);

        $this->withToken($token)->getJson('/api/v1/admin/orders')
            ->assertOk()
            ->assertJsonPath('meta.total', 3);

        $this->withToken($token)->getJson('/api/v1/admin/orders/summary')
            ->assertOk()
            ->assertJsonPath('data.paid_orders', 3)
            ->assertJsonPath('data.revenue', 750)
            ->assertJsonPath('data.refunded', 60);

        foreach ([$this->ownOrder, $this->otherOrder, $this->unassignedOrder] as $order) {
            $this->withToken($token)->getJson("/api/v1/admin/orders/{$order->id}")->assertOk();
        }
    }

    public function test_admin_can_filter_list_totals_and_export_by_seller(): void
    {
        $token = $this->apiLogin($this->admin);
        $query = "seller_id={$this->otherSeller->id}";

        $this->withToken($token)->getJson("/api/v1/admin/orders?{$query}")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $this->otherOrder->id)
            ->assertJsonPath('data.0.seller.id', $this->otherSeller->id);

        $this->withToken($token)->getJson("/api/v1/admin/orders/summary?{$query}")
            ->assertOk()
            ->assertJsonPath('data.paid_orders', 1)
            ->assertJsonPath('data.revenue', 250)
            ->assertJsonPath('data.refunded', 50);

        $this->assertSame([$this->otherOrder->id], $this->exportedOrderIds($token, $query));
    }

    public function test_seller_list_totals_and_export_share_the_same_scope(): void
    {
        $token = $this->apiLogin($this->sellerUser);

        $this->withToken($token)->getJson('/api/v1/admin/orders')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $this->ownOrder->id);

        $this->withToken($token)->getJson('/api/v1/admin/orders/summary')
            ->assertOk()
            ->assertJsonPath('data.paid_orders', 1)
            ->assertJsonPath('data.revenue', 100)
            ->assertJsonPath('data.avg_ticket', 100)
            ->assertJsonPath('data.refunded', 10);

        $this->assertSame([$this->ownOrder->id], $this->exportedOrderIds($token));
    }

    public function test_seller_cannot_widen_the_scope_with_parameters(): void
    {
        $token = $this->apiLogin($this->sellerUser);

        $queries = [
            "seller_id={$this->otherSeller->id}",
            "viewer={$this->admin->id}",
            "search={$this->otherOrder->user->email}",
            "user_id={$this->otherOrder->user_id}",
        ];

        foreach ($queries as $query) {
            $ids = $this->withToken($token)->getJson("/api/v1/admin/orders?{$query}")
                ->assertOk()
                ->json('data.*.id');

            $this->assertEmpty(array_diff($ids, [$this->ownOrder->id]), $query);
            $this->assertEmpty(
                array_diff($this->exportedOrderIds($token, $query), [$this->ownOrder->id]),
                $query,
            );
        }

        $this->withToken($token)->getJson("/api/v1/admin/orders/summary?seller_id={$this->otherSeller->id}")
            ->assertOk()
            ->assertJsonPath('data.paid_orders', 0)
            ->assertJsonPath('data.revenue', 0)
            ->assertJsonPath('data.refunded', 0);

        // Valor não escalar é ignorado pelo filtro, mas o escopo continua valendo.
        $this->withToken($token)->getJson("/api/v1/admin/orders?seller_id[]={$this->otherSeller->id}")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $this->ownOrder->id);
    }

    public function test_seller_opens_only_their_own_orders_by_id(): void
    {
        $token = $this->apiLogin($this->sellerUser);

        // Rota administrativa e rota "me", que também aceita acesso da equipe.
        foreach (['/api/v1/admin/orders', '/api/v1/me/orders'] as $base) {
            $this->withToken($token)->getJson("{$base}/{$this->ownOrder->id}")->assertOk();
            $this->withToken($token)->getJson("{$base}/{$this->otherOrder->id}")->assertForbidden();
            $this->withToken($token)->getJson("{$base}/{$this->unassignedOrder->id}")->assertForbidden();
        }

        $this->withToken($token)->postJson("/api/v1/me/orders/{$this->otherOrder->id}/cancel")
            ->assertForbidden();
    }

    public function test_buying_does_not_turn_an_order_into_the_users_sale(): void
    {
        $purchase = $this->sale($this->otherSeller, 80, $this->sellerUser);
        $token = $this->apiLogin($this->sellerUser);

        $this->withToken($token)->getJson("/api/v1/me/orders/{$purchase->id}")->assertOk();
        $this->withToken($token)->getJson("/api/v1/admin/orders/{$purchase->id}")->assertForbidden();
        $this->withToken($token)->getJson('/api/v1/admin/orders')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_non_admin_without_seller_link_sees_no_sales(): void
    {
        $token = $this->apiLogin($this->staffUser());

        $this->withToken($token)->getJson('/api/v1/admin/orders')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->withToken($token)->getJson('/api/v1/admin/orders/summary')
            ->assertOk()
            ->assertJsonPath('data.paid_orders', 0)
            ->assertJsonPath('data.revenue', 0);

        $this->assertSame([], $this->exportedOrderIds($token));

        $this->withToken($token)->getJson("/api/v1/admin/orders/{$this->unassignedOrder->id}")
            ->assertForbidden();
        $this->withToken($token)->getJson('/api/v1/admin/payments')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
        $this->withToken($token)->getJson('/api/v1/admin/dashboard?period=all')
            ->assertOk()
            ->assertJsonPath('data.kpis.orders_total', 0)
            ->assertJsonPath('data.kpis.revenue', 0);
    }

    public function test_instructor_is_not_treated_as_admin(): void
    {
        $this->sellerUser->userType->update(['code' => 'instructor']);

        $this->withToken($this->apiLogin($this->sellerUser))->getJson('/api/v1/admin/orders')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $this->ownOrder->id);
    }

    public function test_payments_follow_the_sales_scope(): void
    {
        $own = $this->ownOrder->payments()->firstOrFail();
        $other = $this->otherOrder->payments()->firstOrFail();
        $gateway = Gateway::factory()->create(['company_id' => $this->company->id]);
        $token = $this->apiLogin($this->sellerUser);

        $this->withToken($token)->getJson('/api/v1/admin/payments')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $own->id);

        $this->withToken($token)->getJson("/api/v1/admin/payments?order_id={$this->otherOrder->id}")
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->withToken($token)->getJson('/api/v1/admin/payments/summary')
            ->assertOk()
            ->assertJsonPath('data.gross', 100)
            ->assertJsonPath('data.refunded', 10);

        $this->withToken($token)->getJson("/api/v1/admin/payments/{$own->id}")->assertOk();
        $this->withToken($token)->getJson("/api/v1/admin/payments/{$other->id}")->assertForbidden();
        $this->withToken($token)->postJson("/api/v1/admin/payments/{$other->id}/refund")->assertForbidden();
        $this->withToken($token)->postJson('/api/v1/admin/payments', [
            'order_id' => $this->otherOrder->id,
            'gateway_id' => $gateway->id,
            'method' => 'pix',
            'amount' => 10,
        ])->assertForbidden();

        $this->assertSame(3, Payment::query()->withoutGlobalScope('company')->count());

        $this->withToken($this->apiLogin($this->admin))->getJson('/api/v1/admin/payments/summary')
            ->assertOk()
            ->assertJsonPath('data.gross', 750)
            ->assertJsonPath('data.refunded', 60);
    }

    public function test_subscriptions_follow_the_sales_scope(): void
    {
        $own = $this->subscription($this->ownSeller);
        $other = $this->subscription($this->otherSeller);
        $token = $this->apiLogin($this->sellerUser);

        $this->withToken($token)->getJson('/api/v1/admin/subscriptions')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $own->id);

        // A busca usa "ou" e não pode escapar do escopo.
        $this->withToken($token)
            ->getJson('/api/v1/admin/subscriptions?search='.urlencode($this->product->title))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $own->id);

        foreach (['/api/v1/admin/subscriptions', '/api/v1/me/subscriptions'] as $base) {
            $this->withToken($token)->getJson("{$base}/{$own->id}")->assertOk();
            $this->withToken($token)->getJson("{$base}/{$other->id}")->assertForbidden();
            $this->withToken($token)->postJson("{$base}/{$other->id}/cancel")->assertForbidden();
        }

        $this->assertSame(SubscriptionStatus::Active->value, $other->fresh()->status);

        $this->withToken($this->apiLogin($this->admin))->getJson('/api/v1/admin/subscriptions')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_dashboard_sales_figures_follow_the_sales_scope(): void
    {
        foreach (['30', 'all'] as $period) {
            $response = $this->withToken($this->apiLogin($this->sellerUser))
                ->getJson("/api/v1/admin/dashboard?period={$period}")
                ->assertOk()
                ->assertJsonPath('data.kpis.revenue', 90)
                ->assertJsonPath('data.kpis.orders_paid', 1)
                ->assertJsonPath('data.kpis.orders_total', 1)
                ->assertJsonPath('data.top_courses.0.sales', 1)
                ->assertJsonPath('data.top_courses.0.revenue', 100);

            $this->assertSame([$this->ownOrder->id], $response->json('data.recent_orders.*.id'));
            $this->assertSame([$this->ownOrder->user_id], $response->json('data.top_buyers.*.id'));
            $this->assertEquals(90, array_sum($response->json('data.revenue_series.*.revenue')));
            $this->assertSame(1, array_sum($response->json('data.revenue_series.*.orders')));
            $this->assertSame(1, array_sum($response->json('data.order_status.*.count')));
        }

        $this->withToken($this->apiLogin($this->admin))->getJson('/api/v1/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.kpis.revenue', 690)
            ->assertJsonPath('data.kpis.orders_total', 3)
            ->assertJsonPath('data.top_courses.0.sales', 3);
    }

    public function test_sales_audit_log_is_hidden_from_non_admins(): void
    {
        foreach (['order', 'payment', 'subscription', 'seller', 'webhook'] as $module) {
            Datalog::query()->create([
                'company_id' => $this->company->id,
                'module' => $module,
                'action' => 'create',
                'item' => $module,
            ]);
        }

        $sellerToken = $this->apiLogin($this->sellerUser);

        $this->assertSame(
            ['webhook'],
            $this->withToken($sellerToken)->getJson('/api/v1/admin/datalogs')->assertOk()->json('data.*.module'),
        );
        $this->withToken($sellerToken)->getJson('/api/v1/admin/datalogs?module=order')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->withToken($this->apiLogin($this->admin))->getJson('/api/v1/admin/datalogs')
            ->assertOk()
            ->assertJsonPath('meta.total', 5);
    }

    public function test_seller_cannot_list_sellers_or_commissions(): void
    {
        $token = $this->apiLogin($this->sellerUser);

        $this->withToken($token)->getJson('/api/v1/admin/sellers')->assertForbidden();
        $this->withToken($token)->getJson("/api/v1/admin/sellers/{$this->otherSeller->id}")->assertForbidden();
        $this->withToken($token)->getJson('/api/v1/admin/commissions')->assertForbidden();
        $this->withToken($token)->getJson('/api/v1/admin/commissions/summary')->assertForbidden();
    }

    /**
     * Usuário não admin com todos os menus ligados a dados de venda.
     */
    private function staffUser(): User
    {
        $links = [
            '/admin/vendas', '/admin/pagamentos', '/admin/assinaturas',
            '/admin/vendedores', '/admin/comissoes',
        ];

        $menuIds = array_map(fn (string $link) => Menu::query()->firstOrCreate(
            ['link' => $link],
            ['name' => $link, 'type' => 'link', 'sort_order' => 1],
        )->id, $links);

        $type = UserType::factory()->create([
            'company_id' => $this->company->id,
            'code' => null,
            'permission_menu' => $menuIds,
        ]);

        return User::factory()->create([
            'company_id' => $this->company->id,
            'user_type_id' => $type->id,
        ]);
    }

    /**
     * Venda paga com um item, um pagamento e um estorno parcial proporcional
     * (R$ 10 na venda própria, R$ 50 na do outro vendedor, nenhum nas demais).
     */
    private function sale(?Seller $seller, float $total, ?User $buyer = null): Order
    {
        $buyer ??= User::factory()->create([
            'company_id' => $this->company->id,
            'user_type_id' => $this->student->user_type_id,
        ]);

        $order = Order::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $buyer->id,
            'seller_id' => $seller?->id,
            'status' => OrderStatus::Paid->value,
            'subtotal' => $total,
            'total' => $total,
        ]);

        OrderItem::query()->create([
            'company_id' => $this->company->id,
            'order_id' => $order->id,
            'store_product_id' => $this->product->id,
            'product_title' => $this->product->title,
            'quantity' => 1,
            'unit_price' => $total,
            'total' => $total,
        ]);

        $payment = Payment::query()->create([
            'company_id' => $this->company->id,
            'order_id' => $order->id,
            'method' => 'pix',
            'status' => PaymentStatus::Paid->value,
            'amount' => $total,
            'paid_at' => now(),
        ]);

        $refund = [100 => 10, 250 => 50][(int) $total] ?? null;

        if ($refund !== null) {
            Refund::query()->create([
                'company_id' => $this->company->id,
                'payment_id' => $payment->id,
                'amount' => $refund,
                'status' => RefundStatus::Refunded->value,
                'refunded_at' => now(),
            ]);
        }

        return $order;
    }

    private function subscription(Seller $seller): Subscription
    {
        $buyer = User::factory()->create([
            'company_id' => $this->company->id,
            'user_type_id' => $this->student->user_type_id,
        ]);

        return Subscription::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $buyer->id,
            'store_product_id' => $this->product->id,
            'seller_id' => $seller->id,
            'status' => SubscriptionStatus::Active->value,
            'amount' => 50,
        ]);
    }

    /**
     * @return array<int, int>
     */
    private function exportedOrderIds(string $token, string $query = ''): array
    {
        $content = $this->withToken($token)
            ->get("/api/v1/admin/orders/export?{$query}")
            ->assertOk()
            ->streamedContent();

        $path = tempnam(sys_get_temp_dir(), 'sales-');
        file_put_contents($path, $content);

        try {
            $rows = IOFactory::load($path)->getActiveSheet()->toArray();
        } finally {
            @unlink($path);
        }

        $ids = array_filter(array_column(array_slice($rows, 1), 0));

        return array_values(array_map('intval', $ids));
    }
}
