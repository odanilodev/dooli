<?php

namespace App\Http\Resources;

use App\Services\OrderTimelineService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminOrderDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $timeline = app(OrderTimelineService::class)->forOrder($this->resource);
        $refunded = $this->relationLoaded('payments')
            ? $this->payments->flatMap(fn ($payment) => $payment->relationLoaded('refunds') ? $payment->refunds : collect())
            : collect();

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'subscription_id' => $this->subscription_id,
            'type' => $this->subscription_id ? 'subscription' : 'one_time',
            'status' => $this->status,
            'source' => $this->source,
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'total' => $this->total,
            'refunded_total' => round((float) $refunded->sum('amount'), 2),
            'period_start' => $this->period_start?->toIso8601String(),
            'period_end' => $this->period_end?->toIso8601String(),
            'coupon' => new CouponResource($this->whenLoaded('coupon')),
            'seller' => $this->whenLoaded('seller', fn () => $this->seller ? [
                'id' => $this->seller->id,
                'name' => $this->seller->name,
                'code' => $this->seller->code,
            ] : null),
            'referral_code' => $this->referral_code,
            'commission_percent' => $this->commission_percent,
            'commissions' => SellerCommissionResource::collection($this->whenLoaded('sellerCommissions')),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'payments' => AdminPaymentResource::collection($this->whenLoaded('payments')),
            'user' => new UserResource($this->whenLoaded('user')),
            'timeline' => $timeline->map(fn (array $event) => [
                'id' => $event['id'],
                'type' => $event['type'],
                'title' => $event['title'],
                'description' => $event['description'],
                'status' => $event['status'],
                'actor_type' => $event['actor_type'],
                'actor_name' => $event['actor_name'],
                'metadata' => $event['metadata'],
                'occurred_at' => $event['occurred_at'],
                'estimated' => $event['estimated'],
            ])->values(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
