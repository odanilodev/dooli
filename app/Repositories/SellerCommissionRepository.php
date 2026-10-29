<?php

namespace App\Repositories;

use App\Enums\SellerCommissionStatus;
use App\Models\SellerCommission;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class SellerCommissionRepository extends BaseRepository
{
    protected array $filterable = ['seller_id', 'status', 'order_id', 'payment_id', 'company_id'];

    protected string $defaultSort = 'id';

    public function __construct()
    {
        parent::__construct(new SellerCommission);
    }

    public function paginateDetailed(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->paginate(
            perPage: $perPage,
            filters: $filters,
            with: ['seller', 'order.user', 'orderItem'],
            sort: $filters['sort'] ?? null,
            direction: $filters['direction'] ?? 'desc',
        );
    }

    public function filtered(array $filters): Collection
    {
        $query = $this->query()->with(['seller', 'order.user', 'orderItem']);
        $this->applyFilters($query, $filters);

        return $query->latest('id')->get();
    }

    public function findWithRelations(int $id): SellerCommission
    {
        return $this->query()
            ->with(['seller', 'order.user', 'orderItem', 'payment'])
            ->findOrFail($id);
    }

    public function firstOrCreateForItem(int $orderItemId, array $values): SellerCommission
    {
        return $this->query()->firstOrCreate(
            ['order_item_id' => $orderItemId],
            $values,
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{approved: float, paid: float, reversed: float, generated: float, counts: array<string, int>}
     */
    public function summary(array $filters): array
    {
        $query = $this->query();
        $this->applyFilters($query, $filters);

        $rows = (clone $query)
            ->selectRaw('status, COUNT(*) as total_count, COALESCE(SUM(amount), 0) as total_amount, COALESCE(SUM(refunded_amount), 0) as total_refunded')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $counts = [];
        $amounts = [];

        foreach (SellerCommissionStatus::values() as $status) {
            $row = $rows->get($status);
            $counts[$status] = (int) ($row->total_count ?? 0);
            $amounts[$status] = round((float) ($row->total_amount ?? 0) - (float) ($row->total_refunded ?? 0), 2);
        }

        $generated = round((float) (clone $query)->sum('amount'), 2);
        $reversed = round((float) (clone $query)->sum('refunded_amount'), 2);

        return [
            'approved' => $amounts[SellerCommissionStatus::Approved->value] ?? 0,
            'paid' => $amounts[SellerCommissionStatus::Paid->value] ?? 0,
            'reversed' => $reversed,
            'generated' => $generated,
            'counts' => $counts,
        ];
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $productId = $filters['product_id'] ?? null;

        unset($filters['search'], $filters['product_id'], $filters['sort'], $filters['direction']);

        parent::applyFilters($query, $filters);

        if (is_numeric($productId)) {
            $query->whereHas(
                'orderItem',
                fn (Builder $item) => $item->where('store_product_id', (int) $productId),
            );
        }

        if ($search === '') {
            return;
        }

        $query->where(function (Builder $builder) use ($search) {
            $builder
                ->where($this->model->qualifyColumn('order_id'), 'like', "%{$search}%")
                ->orWhereHas('seller', function (Builder $seller) use ($search) {
                    $seller->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                })
                ->orWhereHas('orderItem', function (Builder $item) use ($search) {
                    $item->where('product_title', 'like', "%{$search}%");
                })
                ->orWhereHas('order.user', function (Builder $user) use ($search) {
                    $user->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
        });
    }
}
