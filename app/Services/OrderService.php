<?php

namespace App\Services;

use App\DTO\ApplyCouponDTO;
use App\DTO\CreateOrderDTO;
use App\Enums\GeneralStatus;
use App\Enums\OrderStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\StoreProduct;
use App\Models\User;
use App\Repositories\OrderRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        private readonly OrderRepository $orders,
        private readonly StoreProductService $storeProducts,
        private readonly CouponService $coupons,
        private readonly DatalogService $audit,
    ) {}

    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->orders->paginate(
            perPage: $perPage,
            filters: $filters,
            with: ['items.storeProduct', 'payments'],
            sort: $filters['sort'] ?? null,
            direction: $filters['direction'] ?? 'desc',
        );
    }

    public function find(int $id): Order
    {
        return $this->orders->findWithRelations($id);
    }

    public function create(CreateOrderDTO $dto): Order
    {
        return DB::transaction(function () use ($dto) {
            $items = [];
            $subtotal = 0.0;

            foreach ($dto->items as $item) {
                $storeProduct = $this->storeProducts->find((int) $item['store_product_id']);
                $quantity = max(1, (int) ($item['quantity'] ?? 1));

                $this->assertPurchasable($storeProduct);

                $unitPrice = $storeProduct->effectivePrice();
                $lineTotal = round($unitPrice * $quantity, 2);

                $subtotal += $lineTotal;

                $items[] = [
                    'store_product_id' => $storeProduct->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total' => $lineTotal,
                ];
            }

            $discount = 0.0;
            $coupon = null;

            if ($dto->couponCode) {
                $result = $this->coupons->validate(
                    new ApplyCouponDTO($dto->couponCode, $dto->items),
                    User::query()->findOrFail($dto->userId),
                );

                $coupon = $result['coupon'];
                $discount = $result['discount'];
                $subtotal = $result['subtotal'];
            }

            $order = $this->orders->create([
                'user_id' => $dto->userId,
                'coupon_id' => $coupon?->id,
                'status' => OrderStatus::Pending->value,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => round(max($subtotal - $discount, 0), 2),
            ]);

            $order->items()->createMany($items);

            if ($coupon) {
                $this->coupons->registerUsage($coupon);
            }

            $this->audit->log('order', 'create', 'order', "Pedido criado: {$order->id}", $dto->userId);

            return $order->load(['items.storeProduct', 'coupon']);
        });
    }

    public function cancel(Order $order): Order
    {
        if ($order->status !== OrderStatus::Pending->value) {
            throw new BusinessRuleException('Apenas pedidos pendentes podem ser cancelados.');
        }

        $order = $this->orders->update($order, ['status' => OrderStatus::Canceled->value]);

        $this->audit->log('order', 'cancel', 'order', "Pedido cancelado: {$order->id}", $order->user_id);

        return $order;
    }

    private function assertPurchasable(StoreProduct $storeProduct): void
    {
        if ($storeProduct->status !== GeneralStatus::Active->value) {
            throw new BusinessRuleException("Produto {$storeProduct->title} indisponível.");
        }

        if ($storeProduct->release_at && $storeProduct->release_at->isFuture()) {
            throw new BusinessRuleException("Produto {$storeProduct->title} ainda não está disponível para venda.");
        }
    }
}
