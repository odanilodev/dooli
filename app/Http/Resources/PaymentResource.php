<?php

namespace App\Http\Resources;

use App\Services\PaymentDetailService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $details = app(PaymentDetailService::class)->forPayment($this->resource);

        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'gateway_id' => $this->gateway_id,
            'method' => $this->method,
            'transaction_id' => $this->transaction_id,
            'status' => $this->status,
            'amount' => $this->amount,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'details' => $details ?: null,
            'pix' => app(PaymentDetailService::class)->pix($this->resource) ?: null,
            'refunded_amount' => $this->whenLoaded(
                'refunds',
                fn () => round((float) $this->refunds->sum('amount'), 2),
            ),
            'gateway' => new GatewayResource($this->whenLoaded('gateway')),
            'events' => PaymentEventResource::collection($this->whenLoaded('events')),
            'refunds' => RefundResource::collection($this->whenLoaded('refunds')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
