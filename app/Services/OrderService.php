<?php

namespace App\Services;

use App\DTO\ApplyCouponDTO;
use App\DTO\CreateOrderDTO;
use App\Enums\GeneralStatus;
use App\Enums\OrderEventType;
use App\Enums\OrderSource;
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
        private readonly OrderEventService $events,
        private readonly SellerAttributionService $sellerAttribution,
    ) {}

    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->orders->paginate(
            perPage: $perPage,
            filters: $filters,
            with: ['items.storeProduct', 'latestPayment', 'user', 'seller'],
            sort: $filters['sort'] ?? null,
            direction: $filters['direction'] ?? 'desc',
            withCount: ['items'],
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
            $user = User::query()->findOrFail($dto->userId);
            $seller = $this->sellerAttribution->resolveForUser($user, $dto->referralCode);

            foreach ($dto->items as $item) {
                $storeProduct = $this->storeProducts->find((int) $item['store_product_id']);
                $quantity = max(1, (int) ($item['quantity'] ?? 1));

                $this->assertPurchasable($storeProduct);

                $unitPrice = $storeProduct->effectivePrice();
                $lineTotal = round($unitPrice * $quantity, 2);

                $subtotal += $lineTotal;

                $items[] = [
                    'store_product_id' => $storeProduct->id,
                    'product_title' => $storeProduct->title,
                    'product_access_label' => $storeProduct->accessLabel(),
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
                    $user,
                );

                $coupon = $result['coupon'];
                $discount = $result['discount'];
                $subtotal = $result['subtotal'];
            }

            $order = $this->orders->create([
                'user_id' => $dto->userId,
                'coupon_id' => $coupon?->id,
                'seller_id' => $seller?->id,
                'referral_code' => $seller?->code,
                'commission_percent' => $seller?->commission_percent,
                'status' => OrderStatus::Pending->value,
                'source' => OrderSource::Store->value,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => round(max($subtotal - $discount, 0), 2),
            ]);

            $order->items()->createMany($items);

            if ($coupon) {
                $this->coupons->registerUsage($coupon);
            }

            $this->events->record($order, OrderEventType::OrderCreated, [
                'status' => $order->status,
                'description' => 'Seu pedido foi registrado e está aguardando pagamento.',
                'source_type' => 'order',
                'source_id' => (int) $order->id,
                'occurred_at' => $order->created_at,
                'metadata' => [
                    'subtotal' => (float) $order->subtotal,
                    'discount' => (float) $order->discount,
                    'total' => (float) $order->total,
                    'coupon_code' => $coupon?->code,
                    'seller_id' => $seller?->id,
                    'seller_code' => $seller?->code,
                ],
            ]);

            $this->audit->log('order', 'create', 'order', "Pedido criado: {$order->id}", $dto->userId);

            return $order->load(['items.storeProduct', 'coupon', 'seller']);
        });
    }

    public function cancel(Order $order): Order
    {
        if ($order->status !== OrderStatus::Pending->value) {
            throw new BusinessRuleException('Apenas pedidos pendentes podem ser cancelados.');
        }

        return DB::transaction(function () use ($order) {
            $order = $this->orders->update($order, ['status' => OrderStatus::Canceled->value]);

            $this->events->record($order, OrderEventType::OrderCanceled, [
                'status' => $order->status,
                'description' => 'O pedido foi encerrado antes da confirmação do pagamento.',
                'source_type' => 'order',
                'source_id' => (int) $order->id,
                'occurred_at' => $order->updated_at,
            ]);

            $this->audit->log('order', 'cancel', 'order', "Pedido cancelado: {$order->id}", $order->user_id);

            return $order;
        });
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
