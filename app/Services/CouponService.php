<?php

namespace App\Services;

use App\DTO\ApplyCouponDTO;
use App\Enums\CouponType;
use App\Enums\GeneralStatus;
use App\Exceptions\InvalidCouponException;
use App\Models\Coupon;
use App\Models\StoreProduct;
use App\Models\User;
use App\Repositories\CouponRepository;
use App\Repositories\OrderRepository;
use App\Repositories\StoreProductRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CouponService
{
    public function __construct(
        private readonly CouponRepository $coupons,
        private readonly StoreProductRepository $storeProducts,
        private readonly OrderRepository $orders,
        private readonly DatalogService $audit,
    ) {}

    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->coupons->paginate(
            perPage: $perPage,
            filters: $filters,
            sort: $filters['sort'] ?? null,
            direction: $filters['direction'] ?? 'desc',
        );
    }

    public function find(int $id): Coupon
    {
        return $this->coupons->findWithStoreProducts($id);
    }

    public function create(array $data): Coupon
    {
        $coupon = $this->coupons->create($data);

        if (! empty($data['store_product_ids'])) {
            $coupon->storeProducts()->sync($this->pivotProducts($coupon, $data['store_product_ids']));
        }

        $this->audit->log('coupon', 'create', 'coupon', "Cupom criado: {$coupon->code}");

        return $coupon;
    }

    public function update(Coupon $coupon, array $data): Coupon
    {
        $coupon = $this->coupons->update($coupon, $data);

        if (array_key_exists('store_product_ids', $data)) {
            $coupon->storeProducts()->sync($this->pivotProducts($coupon, $data['store_product_ids'] ?? []));
        }

        return $coupon;
    }

    public function delete(Coupon $coupon): void
    {
        DB::transaction(function () use ($coupon) {
            $coupon->storeProducts()->detach();
            $coupon->delete();
        });

        $this->audit->log('coupon', 'delete', 'coupon', "Cupom excluído: {$coupon->code}");
    }

    /**
     * Validates a coupon and recalculates the discount on the backend.
     * Never trusts a discount amount sent by the client.
     *
     * @return array{coupon: Coupon, subtotal: float, discount: float, total: float}
     */
    public function validate(ApplyCouponDTO $dto, User $user): array
    {
        $coupon = $this->coupons->findActiveByCode($dto->code);

        if (! $coupon) {
            throw new InvalidCouponException('Cupom inexistente ou inativo.', 'coupon_not_found');
        }

        $now = now();

        if ($coupon->starts_at && $coupon->starts_at->isFuture()) {
            throw new InvalidCouponException('Cupom ainda não está válido.', 'coupon_not_started');
        }

        if ($coupon->expires_at && $coupon->expires_at->isPast()) {
            throw new InvalidCouponException('Cupom expirado.', 'coupon_expired');
        }

        if ($coupon->usage_limit !== null && $coupon->usage_count >= $coupon->usage_limit) {
            throw new InvalidCouponException('Cupom atingiu o limite de usos.', 'coupon_usage_limit');
        }

        if ($coupon->per_user_limit !== null) {
            $userUsage = $this->orders->countCouponUsageForUser($coupon->id, $user->id);

            if ($userUsage >= $coupon->per_user_limit) {
                throw new InvalidCouponException('Você já utilizou este cupom o número máximo de vezes.', 'coupon_per_user_limit');
            }
        }

        [$subtotal, $storeProducts] = $this->resolveItems($dto->items);

        if ((float) $coupon->min_order_amount > 0 && $subtotal < (float) $coupon->min_order_amount) {
            throw new InvalidCouponException(
                sprintf('Valor mínimo para este cupom é %s.', number_format((float) $coupon->min_order_amount, 2, ',', '.')),
                'coupon_min_amount',
            );
        }

        $allowedIds = $coupon->storeProducts()->pluck('store_products.id')->all();

        if (! empty($allowedIds)) {
            foreach ($storeProducts as $storeProduct) {
                if (! in_array($storeProduct->id, $allowedIds, true)) {
                    throw new InvalidCouponException('Cupom não é válido para os produtos informados.', 'coupon_product_not_allowed');
                }
            }
        }

        $discount = $this->calculateDiscount($coupon, $subtotal);

        return [
            'coupon' => $coupon,
            'subtotal' => round($subtotal, 2),
            'discount' => $discount,
            'total' => round(max($subtotal - $discount, 0), 2),
        ];
    }

    public function calculateDiscount(Coupon $coupon, float $subtotal): float
    {
        $discount = $coupon->type === CouponType::Percent->value
            ? $subtotal * ((float) $coupon->value / 100)
            : (float) $coupon->value;

        if ($coupon->max_discount !== null) {
            $discount = min($discount, (float) $coupon->max_discount);
        }

        return round(min($discount, $subtotal), 2);
    }

    /**
     * @param  array<int, int|string>  $storeProductIds
     * @return array<int|string, array{company_id: int}>
     */
    private function pivotProducts(Coupon $coupon, array $storeProductIds): array
    {
        return collect($storeProductIds)->mapWithKeys(fn ($id) => [
            $id => ['company_id' => $coupon->company_id],
        ])->all();
    }

    public function registerUsage(Coupon $coupon): void
    {
        $coupon->increment('usage_count');
    }

    /**
     * @param  array<int, array{store_product_id: int, quantity: int}>  $items
     * @return array{0: float, 1: array<int, StoreProduct>}
     */
    public function resolveItems(array $items): array
    {
        $subtotal = 0.0;
        $storeProducts = [];

        foreach ($items as $item) {
            $storeProduct = $this->storeProducts->findOrFail((int) $item['store_product_id']);

            if ($storeProduct->status !== GeneralStatus::Active->value) {
                throw new InvalidCouponException("Produto {$storeProduct->title} indisponível.", 'product_unavailable');
            }

            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $subtotal += $storeProduct->effectivePrice() * $quantity;
            $storeProducts[] = $storeProduct;
        }

        return [$subtotal, $storeProducts];
    }
}
