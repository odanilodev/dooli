<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'gateway_id' => $this->gateway_id,
            'method' => $this->method,
            'transaction_id' => $this->transaction_id,
            'status' => $this->status,
            'amount' => $this->amount,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'metadata' => $this->metadata,
            'gateway' => new GatewayResource($this->whenLoaded('gateway')),
            'events' => PaymentEventResource::collection($this->whenLoaded('events')),
            'refunds' => RefundResource::collection($this->whenLoaded('refunds')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
