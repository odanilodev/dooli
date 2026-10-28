<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_product_id' => $this->store_product_id,
            'product_title' => $this->product_title ?: $this->storeProduct?->title,
            'product_access_label' => $this->product_access_label
                ?: $this->storeProduct?->accessLabel(),
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'total' => $this->total,
            'store_product' => new StoreProductResource($this->whenLoaded('storeProduct')),
        ];
    }
}
