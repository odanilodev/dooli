<?php

namespace App\Repositories;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;

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
            ->with([
                'items.storeProduct.course',
                'payments.gateway',
                'payments.events',
                'payments.refunds',
                'coupon',
                'user',
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

    public function countCouponUsageForUser(int $couponId, int $userId): int
    {
        return $this->query()
            ->where('coupon_id', $couponId)
            ->where('user_id', $userId)
            ->whereNotIn('status', [OrderStatus::Canceled->value])
            ->count();
    }
}
