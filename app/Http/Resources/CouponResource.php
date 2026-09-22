<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CouponResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'type' => $this->type,
            'value' => $this->value,
            'status' => $this->status,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'usage_limit' => $this->usage_limit,
            'usage_count' => $this->usage_count,
            'per_user_limit' => $this->per_user_limit,
            'min_order_amount' => $this->min_order_amount,
            'max_discount' => $this->max_discount,
            'store_products' => StoreProductResource::collection($this->whenLoaded('storeProducts')),
        ];
    }
}
