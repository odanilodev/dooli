<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\SellerCommissionStatus;
use App\Models\Order;
use App\Models\Seller;
use App\Models\SellerCommission;
use App\Models\User;
use App\Repositories\SellerRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class SellerReportService
{
    public function __construct(private readonly SellerRepository $sellers) {}

    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $paginator = $this->sellers->paginate(
            perPage: $perPage,
            filters: $filters,
            with: ['user', 'company'],
            sort: $filters['sort'] ?? null,
            direction: $filters['direction'] ?? 'asc',
        );

        $this->attachMetrics($paginator->getCollection());

        return $paginator;
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(Seller $seller): array
    {
        $seller = $this->sellers->findWithRelations($seller->id);
        $metrics = $this->metricsForIds([$seller->id])[$seller->id] ?? $this->emptyMetrics();

        $products = SellerCommission::query()
            ->join('order_items', 'order_items.id', '=', 'seller_commissions.order_item_id')
            ->join('orders', 'orders.id', '=', 'seller_commissions.order_id')
            ->where('seller_commissions.seller_id', $seller->id)
            ->selectRaw('
                order_items.store_product_id,
                order_items.product_title,
                SUM(order_items.quantity) as quantity,
                COUNT(DISTINCT seller_commissions.order_id) as orders_count,
                COUNT(DISTINCT orders.user_id) as students_count,
                SUM(seller_commissions.base_amount) as base_amount,
                SUM(seller_commissions.amount) as commission_amount,
                SUM(seller_commissions.refunded_amount) as refunded_amount
            ')
            ->groupBy('order_items.store_product_id', 'order_items.product_title')
            ->orderByDesc('commission_amount')
            ->get()
            ->map(fn ($row) => [
                'store_product_id' => (int) $row->store_product_id,
                'product_title' => $row->product_title,
                'quantity' => (int) $row->quantity,
                'orders_count' => (int) $row->orders_count,
                'students_count' => (int) $row->students_count,
                'base_amount' => round((float) $row->base_amount, 2),
                'commission_amount' => round((float) $row->commission_amount, 2),
                'refunded_amount' => round((float) $row->refunded_amount, 2),
                'net_amount' => round((float) $row->commission_amount - (float) $row->refunded_amount, 2),
            ])
            ->values();

        $recentOrders = Order::query()
            ->where('seller_id', $seller->id)
            ->with(['user', 'items', 'latestPayment'])
            ->latest('id')
            ->limit(10)
            ->get();

        $seller->setAttribute('metrics', $metrics);

        return [
            'seller' => $seller,
            'metrics' => $metrics,
            'products' => $products,
            'recent_orders' => $recentOrders,
        ];
    }

    /**
     * @param  Collection<int, Seller>  $sellers
     */
    private function attachMetrics(Collection $sellers): void
    {
        $metrics = $this->metricsForIds($sellers->pluck('id')->map(fn ($id) => (int) $id)->all());

        foreach ($sellers as $seller) {
            $seller->setAttribute('metrics', $metrics[$seller->id] ?? $this->emptyMetrics());
        }
    }

    /**
     * @param  array<int, int>  $sellerIds
     * @return array<int, array<string, float|int>>
     */
    private function metricsForIds(array $sellerIds): array
    {
        if ($sellerIds === []) {
            return [];
        }

        $orders = Order::query()
            ->whereIn('seller_id', $sellerIds)
            ->where('status', OrderStatus::Paid->value)
            ->selectRaw('seller_id, COUNT(*) as paid_orders_count, COUNT(DISTINCT user_id) as students_count, COALESCE(SUM(total), 0) as gross_revenue')
            ->groupBy('seller_id')
            ->get()
            ->keyBy('seller_id');

        $signups = User::query()
            ->whereIn('referred_seller_id', $sellerIds)
            ->selectRaw('referred_seller_id, COUNT(*) as signups_count')
            ->groupBy('referred_seller_id')
            ->get()
            ->keyBy('referred_seller_id');

        $commissions = SellerCommission::query()
            ->whereIn('seller_id', $sellerIds)
            ->selectRaw('seller_id, status, COUNT(*) as total_count, COALESCE(SUM(amount), 0) as total_amount, COALESCE(SUM(refunded_amount), 0) as total_refunded')
            ->groupBy('seller_id', 'status')
            ->get()
            ->groupBy('seller_id');

        $result = [];

        foreach ($sellerIds as $sellerId) {
            $order = $orders->get($sellerId);
            $sellerCommissions = $commissions->get($sellerId, collect());
            $commissionByStatus = $sellerCommissions->keyBy('status');

            $generated = round((float) $sellerCommissions->sum('total_amount'), 2);
            $refunded = round((float) $sellerCommissions->sum('total_refunded'), 2);
            $pending = $this->netFor($commissionByStatus, SellerCommissionStatus::Approved->value);
            $paid = $this->netFor($commissionByStatus, SellerCommissionStatus::Paid->value);

            $result[$sellerId] = [
                'paid_orders_count' => (int) ($order->paid_orders_count ?? 0),
                'students_count' => (int) ($order->students_count ?? 0),
                'signups_count' => (int) ($signups->get($sellerId)?->signups_count ?? 0),
                'gross_revenue' => round((float) ($order->gross_revenue ?? 0), 2),
                'commission_generated' => $generated,
                'commission_pending' => $pending,
                'commission_paid' => $paid,
                'commission_refunded' => $refunded,
                'commission_count' => (int) $sellerCommissions->sum('total_count'),
            ];
        }

        return $result;
    }

    /**
     * @return array<string, float|int>
     */
    private function emptyMetrics(): array
    {
        return [
            'paid_orders_count' => 0,
            'students_count' => 0,
            'signups_count' => 0,
            'gross_revenue' => 0.0,
            'commission_generated' => 0.0,
            'commission_pending' => 0.0,
            'commission_paid' => 0.0,
            'commission_refunded' => 0.0,
            'commission_count' => 0,
        ];
    }

    private function netFor(Collection $rows, string $status): float
    {
        $row = $rows->get($status);

        if (! $row) {
            return 0.0;
        }

        return round((float) $row->total_amount - (float) $row->total_refunded, 2);
    }
}
