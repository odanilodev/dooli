<?php

namespace App\Http\Resources;

use App\Services\PaymentDetailService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $detailService = app(PaymentDetailService::class);

        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'gateway_id' => $this->gateway_id,
            'method' => $this->method,
            'transaction_id' => $this->transaction_id,
            'status' => $this->status,
            'amount' => $this->amount,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'details' => $detailService->forPayment($this->resource) ?: null,
            'pix' => $detailService->pix($this->resource) ?: null,
            'refunded_amount' => $this->whenLoaded(
                'refunds',
                fn () => round((float) $this->refunds->sum('amount'), 2),
            ),
            'gateway' => new GatewayResource($this->whenLoaded('gateway')),
            'events' => PaymentEventResource::collection($this->whenLoaded('events')),
            'refunds' => RefundResource::collection($this->whenLoaded('refunds')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'order' => $this->whenLoaded('order', fn () => [
                'id' => $this->order->id,
                'status' => $this->order->status,
                'total' => $this->order->total,
                'created_at' => $this->order->created_at?->toIso8601String(),
                'user' => $this->order->relationLoaded('user') && $this->order->user
                    ? [
                        'id' => $this->order->user->id,
                        'name' => $this->order->user->name,
                        'email' => $this->order->user->email,
                    ]
                    : null,
            ]),
        ];
    }
}
