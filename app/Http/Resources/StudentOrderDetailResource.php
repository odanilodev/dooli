<?php

namespace App\Http\Resources;

use App\Services\OrderTimelineService;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentOrderDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $timeline = app(OrderTimelineService::class)->forOrder($this->resource);
        $refund = app(PaymentService::class)->refundEligibility($this->resource);

        return [
            'id' => $this->id,
            'subscription_id' => $this->subscription_id,
            'type' => $this->subscription_id ? 'subscription' : 'one_time',
            'status' => $this->status,
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'total' => $this->total,
            'period_start' => $this->period_start?->toIso8601String(),
            'period_end' => $this->period_end?->toIso8601String(),
            'refund' => [
                'eligible' => $refund['eligible'],
                'deadline' => $refund['deadline']?->toIso8601String(),
                'window_days' => PaymentService::REFUND_WINDOW_DAYS,
                'message' => $refund['message'],
            ],
            'coupon' => new CouponResource($this->whenLoaded('coupon')),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'payments' => StudentPaymentResource::collection($this->whenLoaded('payments')),
            'timeline' => $timeline->map(fn (array $event) => [
                'id' => $event['id'],
                'type' => $event['type'],
                'title' => $event['title'],
                'description' => $event['description'],
                'status' => $event['status'],
                'occurred_at' => $event['occurred_at'],
            ])->values(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
