<?php

namespace App\Repositories;

use App\Enums\OrderStatus;
use App\Models\Order;

class OrderRepository extends BaseRepository
{
    protected array $searchable = ['status'];

    protected array $filterable = ['status', 'user_id', 'company_id', 'coupon_id'];

    protected string $defaultSort = 'id';

    public function __construct()
    {
        parent::__construct(new Order);
    }

    public function findWithRelations(int $id): Order
    {
        return $this->query()
            ->with(['items.storeProduct.course', 'payments.gateway', 'coupon', 'user'])
            ->findOrFail($id);
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
