<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'amount' => $this->amount,
            'current_period_start' => $this->current_period_start?->toIso8601String(),
            'current_period_end' => $this->current_period_end?->toIso8601String(),
            'grace_ends_at' => $this->grace_ends_at?->toIso8601String(),
            'next_billing_at' => $this->next_billing_at?->toIso8601String(),
            'canceled_at' => $this->canceled_at?->toIso8601String(),
            'store_product' => new StoreProductResource($this->whenLoaded('storeProduct')),
            'gateway' => new GatewayResource($this->whenLoaded('gateway')),
            'user' => new UserResource($this->whenLoaded('user')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
