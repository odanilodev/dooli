<?php

namespace App\Repositories;

use App\Enums\OrderStatus;
use App\Enums\RefundStatus;
use App\Models\Order;
use App\Models\Refund;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class OrderRepository extends BaseRepository
{
    protected array $searchable = ['status'];

    protected array $filterable = ['status', 'source', 'user_id', 'company_id', 'coupon_id', 'seller_id'];

    protected string $defaultSort = 'id';

    public function __construct()
    {
        parent::__construct(new Order);
    }

    public function findWithRelations(int $id): Order
    {
        return $this->query()
            ->with([
                'items.storeProduct.course',
                'payments.gateway',
                'payments.events',
                'payments.refunds',
                'coupon',
                'user',
                'seller',
                'sellerCommissions.seller',
            ])
            ->findOrFail($id);
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        $search = trim((string) ($filters['search'] ?? ''));
        unset($filters['search']);

        parent::applyFilters($query, $filters);

        if ($search === '') {
            return;
        }

        $query->where(function (Builder $builder) use ($search) {
            $builder
                ->where($this->model->qualifyColumn('status'), 'like', "%{$search}%")
                ->orWhere($this->model->qualifyColumn('id'), 'like', "%{$search}%")
                ->orWhereHas('user', function (Builder $user) use ($search) {
                    $user->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{paid_orders: int, revenue: float, avg_ticket: float, refunded: float}
     */
    public function summary(array $filters): array
    {
        $query = $this->query();
        $this->applyFilters($query, $filters);

        $paidQuery = (clone $query)->where(
            $this->model->qualifyColumn('status'),
            OrderStatus::Paid->value,
        );

        $paidOrders = (int) (clone $paidQuery)->count();
        $revenue = round((float) (clone $paidQuery)->sum($this->model->qualifyColumn('total')), 2);
        $orderIds = (clone $query)->select($this->model->qualifyColumn('id'));

        $refunded = round((float) Refund::query()
            ->where('status', RefundStatus::Refunded->value)
            ->whereHas('payment', fn (Builder $payment) => $payment->whereIn('order_id', $orderIds))
            ->sum('amount'), 2);

        return [
            'paid_orders' => $paidOrders,
            'revenue' => $revenue,
            'avg_ticket' => $paidOrders > 0 ? round($revenue / $paidOrders, 2) : 0.0,
            'refunded' => $refunded,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Order>
     */
    public function filtered(array $filters): Collection
    {
        $query = $this->query()->with(['coupon', 'items.storeProduct', 'latestPayment', 'seller', 'user']);
        $this->applyFilters($query, $filters);

        return $query->latest($this->model->qualifyColumn('id'))->get();
    }

    public function countCouponUsageForUser(int $couponId, int $userId): int
    {
        return $this->query()
            ->where('coupon_id', $couponId)
            ->where('user_id', $userId)
            ->whereNotIn('status', [OrderStatus::Canceled->value])
            ->count();
    }
}
